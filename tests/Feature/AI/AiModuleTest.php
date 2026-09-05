<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Enums\AiIntent;
use App\AI\Exceptions\AiProviderException;
use App\AI\Providers\FakeAiProvider;
use App\AI\Services\AiQueryService;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUserA;
    protected User $employeeUserA;
    protected User $adminUserB;
    protected FakeAiProvider $fakeProvider;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);

        $this->fakeProvider = new FakeAiProvider();
        $this->app->instance(AiProviderInterface::class, $this->fakeProvider);

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->businessA = Business::create(['name' => 'Emprendimiento A']);
        $this->businessB = Business::create(['name' => 'Emprendimiento B']);

        $this->adminUserA = User::factory()->create();
        $this->businessA->users()->attach($this->adminUserA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUserA = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUserA->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->adminUserB = User::factory()->create();
        $this->businessB->users()->attach($this->adminUserB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);
    }

    public function test_module_can_be_disabled_via_config_without_affecting_core(): void
    {
        // 1. Desactivar el módulo de IA
        config(['ai.enabled' => false]);

        $aiResponse = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', ['query' => '¿Ventas de hoy?']);

        $aiResponse->assertStatus(200);
        $aiResponse->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('desactivado', $aiResponse->json('summary'));

        // 2. Comprobar que TODOS los módulos del núcleo funcionan perfectamente con 200 OK
        $coreRoutes = [
            '/products',
            '/inventory',
            '/sales',
            '/customers',
            '/suppliers',
            '/reports',
        ];

        foreach ($coreRoutes as $route) {
            $response = $this->actingAs($this->adminUserA)
                ->withSession(['current_business_id' => $this->businessA->id])
                ->get($route);

            $response->assertStatus(200);
        }
    }

    public function test_unauthorized_intent_is_strictly_rejected(): void
    {
        // El proveedor simula devolver una intención arbitraria no autorizada
        $this->fakeProvider->setNextIntent('drop_tables', ['table' => 'users']);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', ['query' => 'Eliminar base de datos']);

        $response->assertStatus(200);
        $response->assertJson([
            'intent' => 'unauthorized',
            'success' => false,
        ]);
        $this->assertStringContainsString('no corresponde a una operación de lectura permitida', $response->json('summary'));
    }

    public function test_business_id_is_strictly_injected_by_laravel_preventing_cross_tenant_access(): void
    {
        // Tenant A: Producto en bajo stock
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Café Tenant A',
            'sku' => 'CFE-A',
            'stock' => 2,
            'min_stock' => 5,
            'cost_price' => 1000,
            'sale_price' => 2000,
            'is_active' => true,
        ]);

        // Tenant B: Producto en bajo stock
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Café Tenant B',
            'sku' => 'CFE-B',
            'stock' => 1,
            'min_stock' => 10,
            'cost_price' => 5000,
            'sale_price' => 8000,
            'is_active' => true,
        ]);

        $this->fakeProvider->setNextIntent(AiIntent::LOW_STOCK_PRODUCTS);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', ['query' => '¿Cuáles productos tienen bajo stock?']);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Café Tenant A']);
        $this->assertStringNotContainsString('Café Tenant B', $response->getContent());
    }

    public function test_read_only_restriction_is_preserved(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto Prueba',
            'sku' => 'TEST-01',
            'stock' => 10,
            'min_stock' => 5,
            'cost_price' => 1000,
            'sale_price' => 2000,
            'is_active' => true,
        ]);

        $this->fakeProvider->setNextIntent(AiIntent::LIST_PRODUCTS);

        $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', ['query' => 'Listar productos']);

        // Verificar que el stock y registros permanezcan intactos
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
        ]);
    }

    public function test_provider_timeout_or_error_returns_friendly_fallback(): void
    {
        $this->fakeProvider->setException(new AiProviderException('Timeout de 10 segundos excedido'));

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', ['query' => '¿Ventas de hoy?']);

        $response->assertStatus(200);
        $response->assertJson([
            'intent' => 'fallback',
            'summary' => AiQueryService::FALLBACK_MESSAGE,
            'success' => false,
        ]);
        // Verificar que no se filtre el stack trace ni el mensaje técnico
        $this->assertStringNotContainsString('Timeout de 10 segundos', $response->getContent());
    }

    public function test_all_nine_whitelisted_intents_execute_successfully_and_return_data(): void
    {
        // Setup data
        Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Carlos Pérez',
            'is_active' => true,
        ]);

        $prod = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Arroz Integral',
            'sku' => 'ARR-01',
            'stock' => 3,
            'min_stock' => 5,
            'cost_price' => 2000,
            'sale_price' => 4000,
            'is_active' => true,
        ]);

        $outProd = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Frijol Negro',
            'sku' => 'FRJ-02',
            'stock' => 0,
            'min_stock' => 5,
            'cost_price' => 3000,
            'sale_price' => 6000,
            'is_active' => true,
        ]);

        $sale = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-AI-01',
            'sale_date' => Carbon::today(),
            'subtotal' => 20000,
            'discount' => 0,
            'total' => 20000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $prod->id,
            'quantity' => 5,
            'unit_price' => 4000,
            'subtotal' => 20000,
        ]);

        $intentsToTest = [
            AiIntent::LIST_CUSTOMERS,
            AiIntent::COUNT_CUSTOMERS,
            AiIntent::LIST_PRODUCTS,
            AiIntent::LOW_STOCK_PRODUCTS,
            AiIntent::OUT_OF_STOCK_PRODUCTS,
            AiIntent::TOP_SELLING_PRODUCTS,
            AiIntent::SALES_SUMMARY,
            AiIntent::SALES_BY_PERIOD,
            AiIntent::INVENTORY_SUMMARY,
        ];

        foreach ($intentsToTest as $intent) {
            $this->fakeProvider->setNextIntent($intent);

            $response = $this->actingAs($this->adminUserA)
                ->withSession(['current_business_id' => $this->businessA->id])
                ->postJson('/ai/ask', ['query' => 'consulta de prueba']);

            $response->assertStatus(200);
            $response->assertJson([
                'intent' => $intent,
                'success' => true,
            ]);
            $this->assertNotEmpty($response->json('summary'));
        }
    }
}
