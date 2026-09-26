<?php

namespace Tests\Feature\Sales;

use App\Exceptions\Sales\InvalidSaleItemException;
use App\Models\Business;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleServiceErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected Role $employeeRole;
    protected Business $tenantA;
    protected Business $tenantB;
    protected User $userA;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->tenantA = Business::create(['name' => 'Tenant A Cali']);
        $this->tenantB = Business::create(['name' => 'Tenant B Bogota']);

        $this->userA = User::factory()->create();
        $this->tenantA->users()->attach($this->userA->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $catA = Category::withoutGlobalScopes()->create([
            'business_id' => $this->tenantA->id,
            'name' => 'Categoria A',
        ]);

        $catB = Category::withoutGlobalScopes()->create([
            'business_id' => $this->tenantB->id,
            'name' => 'Categoria B',
        ]);

        $this->productA = Product::withoutGlobalScopes()->create([
            'business_id' => $this->tenantA->id,
            'category_id' => $catA->id,
            'name' => 'Producto A',
            'sku' => 'SKU-A-01',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'business_id' => $this->tenantB->id,
            'category_id' => $catB->id,
            'name' => 'Producto B',
            'sku' => 'SKU-B-01',
            'cost_price' => 15000,
            'sale_price' => 30000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Paso 1 & 3 - Caso 1: Enviar POST /sales con product_id de Tenant B.
     * Es interceptado en la primera línea de defensa (StoreSaleRequest).
     */
    public function test_post_sale_with_other_tenant_product_fails_at_validation_layer(): void
    {
        $response = $this->actingAs($this->userA)
            ->withSession(['current_business_id' => $this->tenantA->id])
            ->post('/sales', [
                'sale_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'discount_percentage' => 0,
                'items' => [
                    [
                        'product_id' => $this->productB->id, // Producto de Tenant B
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['items.0.product_id']);

        $this->assertEquals(0, Sale::count());
        $this->assertEquals(0, SaleDetail::count());
        $this->assertEquals(10, $this->productB->fresh()->stock);
        $this->assertEquals(10, $this->productA->fresh()->stock);
    }

    /**
     * Paso 1 & 3 - Caso 2: Enviar POST /sales con product_id inexistente (999999999).
     * Es interceptado en StoreSaleRequest.
     */
    public function test_post_sale_with_nonexistent_product_fails_at_validation_layer(): void
    {
        $response = $this->actingAs($this->userA)
            ->withSession(['current_business_id' => $this->tenantA->id])
            ->post('/sales', [
                'sale_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'discount_percentage' => 0,
                'items' => [
                    [
                        'product_id' => 999999999,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['items.0.product_id']);

        $this->assertEquals(0, Sale::count());
        $this->assertEquals(0, SaleDetail::count());
        $this->assertEquals(10, $this->productA->fresh()->stock);
    }

    /**
     * Paso 2 & 3 - Caso 3: Invocación directa a SaleService::processSale con producto de Tenant B.
     * Lanza la excepción de dominio InvalidSaleItemException con mensaje genérico seguro.
     */
    public function test_sale_service_direct_call_with_other_tenant_product_throws_domain_exception(): void
    {
        $service = app(SaleService::class);

        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'discount_percentage' => 0,
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $this->expectException(InvalidSaleItemException::class);
        $this->expectExceptionMessage('El producto seleccionado no está disponible en este negocio o ya no existe.');

        $service->processSale($payload, $this->tenantA->id, $this->userA->id);
    }

    /**
     * Paso 2 & 3 - Caso 4: Invocación directa a SaleService::processSale con producto inexistente.
     * Lanza la misma excepción de dominio con idéntico mensaje genérico para evitar enumeración.
     */
    public function test_sale_service_direct_call_with_nonexistent_product_throws_domain_exception(): void
    {
        $service = app(SaleService::class);

        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'discount_percentage' => 0,
            'items' => [
                [
                    'product_id' => 999999999,
                    'quantity' => 1,
                ],
            ],
        ];

        $this->expectException(InvalidSaleItemException::class);
        $this->expectExceptionMessage('El producto seleccionado no está disponible en este negocio o ya no existe.');

        $service->processSale($payload, $this->tenantA->id, $this->userA->id);
    }

    /**
     * Paso 3 - Caso 5: Rollback transaccional completo en carrito múltiple.
     * Si el carrito contiene un producto válido (productA) y uno inválido (productB de otro tenant),
     * la transacción se revierte al 100%: no se crea venta ni se descuenta stock de productA.
     */
    public function test_transaction_rolls_back_completely_and_preserves_stock_when_multi_item_cart_has_invalid_item(): void
    {
        $service = app(SaleService::class);

        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'discount_percentage' => 0,
            'items' => [
                [
                    'product_id' => $this->productA->id, // Producto válido con stock 10
                    'quantity' => 3,
                ],
                [
                    'product_id' => $this->productB->id, // Producto inválido (Tenant B)
                    'quantity' => 1,
                ],
            ],
        ];

        try {
            $service->processSale($payload, $this->tenantA->id, $this->userA->id);
            $this->fail('Se esperaba InvalidSaleItemException pero no se lanzó.');
        } catch (InvalidSaleItemException $e) {
            $this->assertEquals(
                'El producto seleccionado no está disponible en este negocio o ya no existe.',
                $e->getMessage()
            );
        }

        // Ningún registro creado
        $this->assertEquals(0, Sale::count());
        $this->assertEquals(0, SaleDetail::count());
        $this->assertEquals(0, InventoryMovement::count());

        // El stock del producto legítimo A NUNCA fue descontado (sigue en 10)
        $this->assertEquals(10, $this->productA->fresh()->stock);
        $this->assertEquals(10, $this->productB->fresh()->stock);
    }

    /**
     * Paso 3 - Caso 6: SaleController captura InvalidSaleItemException y devuelve redirección controlada.
     */
    public function test_controller_handles_invalid_sale_item_exception_and_redirects_with_friendly_error(): void
    {
        // Mockeamos SaleService para simular la excepción en SaleController::store
        $mockService = $this->createMock(SaleService::class);
        $mockService->method('processSale')
            ->willThrowException(new InvalidSaleItemException(
                'El producto seleccionado no está disponible en este negocio o ya no existe.'
            ));

        $this->app->instance(SaleService::class, $mockService);

        $response = $this->actingAs($this->userA)
            ->withSession(['current_business_id' => $this->tenantA->id])
            ->post('/sales', [
                'sale_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'discount_percentage' => 0,
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'items' => 'El producto seleccionado no está disponible en este negocio o ya no existe.',
        ]);
    }
}
