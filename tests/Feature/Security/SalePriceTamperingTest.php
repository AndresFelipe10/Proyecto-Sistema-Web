<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePriceTamperingTest extends TestCase
{
    use RefreshDatabase;

    protected Role $employeeRole;
    protected Business $tenantA;
    protected User $employee;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->tenantA = Business::create(['name' => 'Empresa Test A']);

        $this->employee = User::factory()->create();
        $this->tenantA->users()->attach($this->employee->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'business_id' => $this->tenantA->id,
            'name' => 'General',
        ]);

        $this->product = Product::create([
            'business_id' => $this->tenantA->id,
            'category_id' => $category->id,
            'name' => 'Producto Caro',
            'sku' => 'PROD-EXPENSIVE-01',
            'cost_price' => 20000.00,
            'sale_price' => 50000.00,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Post-fix test 1: El precio manipulado hacia abajo (undercharge) es ignorado.
     * El backend resuelve authoritativamente 50000.00 desde products.sale_price.
     */
    public function test_client_cannot_tamper_unit_price_downwards(): void
    {
        $response = $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->tenantA->id])
            ->post('/sales', [
                'sale_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'discount_percentage' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                        'unit_price' => 100.00, // Intento de subfacturación / undercharge
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $sale = Sale::first();
        $this->assertNotNull($sale);

        $detail = SaleDetail::where('sale_id', $sale->id)->first();
        $this->assertNotNull($detail);

        // El precio unitario y total deben ser 50000.00, ignorando los 100.00 enviados
        $this->assertEquals('50000.00', (string) $detail->unit_price);
        $this->assertEquals('50000.00', (string) $detail->subtotal);
        $this->assertEquals('50000.00', (string) $sale->subtotal);
        $this->assertEquals('50000.00', (string) $sale->total);

        // El stock se descuenta legítimamente (10 -> 9)
        $this->assertEquals(9, $this->product->fresh()->stock);
    }

    /**
     * Post-fix test 2: El precio manipulado hacia arriba (overcharge) también es ignorado.
     * Previene inflar fraudulentamente métricas de venta o generar facturas infladas.
     */
    public function test_client_cannot_tamper_unit_price_upwards(): void
    {
        $response = $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->tenantA->id])
            ->post('/sales', [
                'sale_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'discount_percentage' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2,
                        'unit_price' => 999999.00, // Intento de sobrefacturación / overcharge
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $sale = Sale::first();
        $this->assertNotNull($sale);

        $detail = SaleDetail::where('sale_id', $sale->id)->first();
        $this->assertNotNull($detail);

        // Debe registrar 2 unidades x 50000.00 = 100000.00
        $this->assertEquals('50000.00', (string) $detail->unit_price);
        $this->assertEquals('100000.00', (string) $detail->subtotal);
        $this->assertEquals('100000.00', (string) $sale->subtotal);
        $this->assertEquals('100000.00', (string) $sale->total);

        // El stock se descuenta adecuadamente (10 -> 8)
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    /**
     * Post-fix test 3: Validación de rango razonable en el campo quantity (máximo 99999).
     */
    public function test_quantity_cannot_exceed_maximum_limit(): void
    {
        $response = $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->tenantA->id])
            ->post('/sales', [
                'sale_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'discount_percentage' => 0,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 100000, // Supera max:99999
                        'unit_price' => 50000.00,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['items.0.quantity']);
        $this->assertEquals(0, Sale::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    /**
     * Post-fix test 4: Venta legítima con descuento porcentual calculado estrictamente en backend.
     */
    public function test_legitimate_sale_with_discount_calculated_authoritatively_in_backend(): void
    {
        $response = $this->actingAs($this->employee)
            ->withSession(['current_business_id' => $this->tenantA->id])
            ->post('/sales', [
                'sale_date' => now()->toDateString(),
                'payment_method' => 'card',
                'discount_percentage' => 10, // 10% de descuento
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $sale = Sale::first();
        $this->assertNotNull($sale);

        // Subtotal: 50000.00, Descuento 10%: 5000.00, Total: 45000.00
        $this->assertEquals('50000.00', (string) $sale->subtotal);
        $this->assertEquals('5000.00', (string) $sale->discount);
        $this->assertEquals('45000.00', (string) $sale->total);
        $this->assertEquals(9, $this->product->fresh()->stock);
    }
}
