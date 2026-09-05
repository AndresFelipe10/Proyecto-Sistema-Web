<?php

namespace Tests\Feature\Inventory;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\InventoryIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUserA;
    protected User $employeeUserA;
    protected User $adminUserB;
    protected InventoryIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryIntelligenceService();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->businessA = Business::create(['name' => 'Emprendimiento Cali A']);
        $this->businessB = Business::create(['name' => 'Emprendimiento Cali B']);

        $this->adminUserA = User::factory()->create();
        $this->businessA->users()->attach($this->adminUserA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUserA = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUserA->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->adminUserB = User::factory()->create();
        $this->businessB->users()->attach($this->adminUserB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);
    }

    public function test_classifies_product_as_out_of_stock_when_stock_is_zero_or_negative(): void
    {
        $this->assertEquals(
            InventoryIntelligenceService::STATUS_OUT_OF_STOCK,
            $this->service->classify(0, 10)
        );

        $this->assertEquals(
            InventoryIntelligenceService::STATUS_OUT_OF_STOCK,
            $this->service->classify(-2, 5)
        );
    }

    public function test_classifies_product_as_low_stock_when_stock_is_at_or_below_min_stock(): void
    {
        // Caso exacto frontera: stock == min_stock
        $this->assertEquals(
            InventoryIntelligenceService::STATUS_LOW_STOCK,
            $this->service->classify(5, 5)
        );

        // Caso intermedio: 0 < stock < min_stock
        $this->assertEquals(
            InventoryIntelligenceService::STATUS_LOW_STOCK,
            $this->service->classify(2, 10)
        );
    }

    public function test_classifies_product_as_normal_when_stock_exceeds_min_stock(): void
    {
        // Caso frontera inmediato: stock == min_stock + 1
        $this->assertEquals(
            InventoryIntelligenceService::STATUS_NORMAL,
            $this->service->classify(6, 5)
        );

        // Caso holgado
        $this->assertEquals(
            InventoryIntelligenceService::STATUS_NORMAL,
            $this->service->classify(50, 10)
        );
    }

    public function test_calculates_replenishment_recommendation_accurately(): void
    {
        $productLow = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Café Especial',
            'sku' => 'CFE-001',
            'cost_price' => 15000,
            'sale_price' => 25000,
            'stock' => 2,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        $productOut = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Té Chai',
            'sku' => 'TE-002',
            'cost_price' => 5000,
            'sale_price' => 10000,
            'stock' => 0,
            'min_stock' => 6,
            'is_active' => true,
        ]);

        $productNormal = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Panela Orgánica',
            'sku' => 'PAN-003',
            'cost_price' => 3000,
            'sale_price' => 6000,
            'stock' => 20,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        // 1. Producto en bajo stock
        $repLow = $this->service->calculateReplenishment($productLow);
        $this->assertEquals(InventoryIntelligenceService::STATUS_LOW_STOCK, $repLow['status']);
        $this->assertEquals(8, $repLow['deficit']); // 10 - 2 = 8
        $this->assertEquals(18, $repLow['suggested_qty']); // (10 * 2) - 2 = 18
        $this->assertEquals(270000.0, $repLow['estimated_cost']); // 18 * 15000 = 270000

        // 2. Producto agotado
        $repOut = $this->service->calculateReplenishment($productOut);
        $this->assertEquals(InventoryIntelligenceService::STATUS_OUT_OF_STOCK, $repOut['status']);
        $this->assertEquals(6, $repOut['deficit']); // 6 - 0 = 6
        $this->assertEquals(12, $repOut['suggested_qty']); // (6 * 2) - 0 = 12
        $this->assertEquals(60000.0, $repOut['estimated_cost']); // 12 * 5000 = 60000

        // 3. Producto normal
        $repNormal = $this->service->calculateReplenishment($productNormal);
        $this->assertEquals(InventoryIntelligenceService::STATUS_NORMAL, $repNormal['status']);
        $this->assertEquals(0, $repNormal['deficit']);
        $this->assertEquals(0, $repNormal['suggested_qty']);
        $this->assertEquals(0.0, $repNormal['estimated_cost']);
    }

    public function test_smart_panel_isolates_data_between_tenants(): void
    {
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto Negocio A Crítico',
            'sku' => 'PROD-A-01',
            'cost_price' => 10000,
            'sale_price' => 18000,
            'stock' => 1,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Producto Negocio B Agotado',
            'sku' => 'PROD-B-01',
            'cost_price' => 20000,
            'sale_price' => 35000,
            'stock' => 0,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        $responseA = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/inventory/alerts');

        $responseA->assertStatus(200);
        $responseA->assertSee('Producto Negocio A Crítico');
        $responseA->assertDontSee('Producto Negocio B Agotado');
        $responseA->assertViewHas('low_stock_count', 1);
        $responseA->assertViewHas('out_of_stock_count', 0);

        $responseB = $this->actingAs($this->adminUserB)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->get('/inventory/alerts');

        $responseB->assertStatus(200);
        $responseB->assertSee('Producto Negocio B Agotado');
        $responseB->assertDontSee('Producto Negocio A Crítico');
        $responseB->assertViewHas('out_of_stock_count', 1);
        $responseB->assertViewHas('low_stock_count', 0);
    }

    public function test_smart_panel_filters_by_status_and_search(): void
    {
        $cat1 = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Bebidas',
        ]);

        $cat2 = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Snacks',
        ]);

        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'category_id' => $cat1->id,
            'name' => 'Jugo de Naranja',
            'sku' => 'JUG-01',
            'cost_price' => 2000,
            'sale_price' => 4000,
            'stock' => 0,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'category_id' => $cat2->id,
            'name' => 'Papas Fritas',
            'sku' => 'PAP-01',
            'cost_price' => 1500,
            'sale_price' => 3000,
            'stock' => 20,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        // Filtrar por status=out_of_stock
        $resFilterStatus = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/inventory/alerts?status=out_of_stock');

        $resFilterStatus->assertStatus(200);
        $resFilterStatus->assertSee('Jugo de Naranja');
        $resFilterStatus->assertDontSee('Papas Fritas');

        // Filtrar por búsqueda 'Papas'
        $resSearch = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/inventory/alerts?search=Papas');

        $resSearch->assertStatus(200);
        $resSearch->assertSee('Papas Fritas');
        $resSearch->assertDontSee('Jugo de Naranja');
    }

    public function test_employee_can_view_smart_inventory_panel(): void
    {
        $response = $this->actingAs($this->employeeUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/inventory/alerts');

        $response->assertStatus(200);
        $response->assertSee('Panel Inteligente de Inventario');
    }
}
