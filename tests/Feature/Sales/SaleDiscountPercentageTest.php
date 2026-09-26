<?php

namespace Tests\Feature\Sales;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleDiscountPercentageTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $business;
    protected User $adminUser;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->business = Business::create(['name' => 'Negocio Test Descuento']);

        $this->adminUser = User::factory()->create();
        $this->business->users()->attach($this->adminUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->business->id,
            'name' => 'General',
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->business->id,
            'category_id' => $category->id,
            'name' => 'Producto Test',
            'sku' => 'TST-001',
            'cost_price' => 10000,
            'sale_price' => 50000,
            'stock' => 20,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    protected function salePayload(array $overrides = []): array
    {
        return array_merge([
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => null,
            'discount_percentage' => 0,
            'payment_method' => 'cash',
            'notes' => null,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 50000,
                ],
            ],
        ], $overrides);
    }

    public function test_sale_with_known_discount_percentage_calculates_correctly(): void
    {
        // subtotal = 2 * 50000 = 100000
        // discount_percentage = 10 → discount = 100000 * 0.10 = 10000
        // total = 100000 - 10000 = 90000
        $payload = $this->salePayload(['discount_percentage' => 10]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();

        $sale = Sale::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->first();

        $this->assertNotNull($sale);
        $this->assertEquals('10.00', $sale->discount_percentage);
        $this->assertEquals('10000.00', $sale->discount);
        $this->assertEquals('100000.00', $sale->subtotal);
        $this->assertEquals('90000.00', $sale->total);
    }

    public function test_sale_with_zero_discount_percentage(): void
    {
        $payload = $this->salePayload(['discount_percentage' => 0]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();

        $sale = Sale::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->first();

        $this->assertEquals('0.00', $sale->discount_percentage);
        $this->assertEquals('0.00', $sale->discount);
        $this->assertEquals('100000.00', $sale->subtotal);
        $this->assertEquals('100000.00', $sale->total);
    }

    public function test_sale_with_100_percent_discount(): void
    {
        $payload = $this->salePayload(['discount_percentage' => 100]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();

        $sale = Sale::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->first();

        $this->assertEquals('100.00', $sale->discount_percentage);
        $this->assertEquals('100000.00', $sale->discount);
        $this->assertEquals('0.00', $sale->total);
    }

    public function test_discount_percentage_above_100_is_rejected(): void
    {
        $payload = $this->salePayload(['discount_percentage' => 150]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('sales.store'), $payload);

        $response->assertSessionHasErrors('discount_percentage');
    }

    public function test_discount_percentage_below_zero_is_rejected(): void
    {
        $payload = $this->salePayload(['discount_percentage' => -5]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('sales.store'), $payload);

        $response->assertSessionHasErrors('discount_percentage');
    }

    public function test_discount_percentage_with_fractional_value(): void
    {
        // subtotal = 100000, discount_percentage = 15.5
        // discount = 100000 * 0.155 = 15500
        // total = 100000 - 15500 = 84500
        $payload = $this->salePayload(['discount_percentage' => 15.5]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();

        $sale = Sale::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->first();

        $this->assertEquals('15.50', $sale->discount_percentage);
        $this->assertEquals('15500.00', $sale->discount);
        $this->assertEquals('84500.00', $sale->total);
    }
}
