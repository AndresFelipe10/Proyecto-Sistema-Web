<?php

namespace Tests\Feature\Sales;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUser;
    protected User $employeeUser;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->businessA = Business::create(['name' => 'Negocio Cali A']);
        $this->businessB = Business::create(['name' => 'Negocio Cali B']);

        $this->adminUser = User::factory()->create();
        $this->businessA->users()->attach($this->adminUser->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUser = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUser->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        // Create products with stock
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'General',
        ]);

        $this->productA = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'category_id' => $category->id,
            'name' => 'Camiseta Deportiva',
            'sku' => 'CAM-001',
            'cost_price' => 15000,
            'sale_price' => 35000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'category_id' => $category->id,
            'name' => 'Pantalón Casual',
            'sku' => 'PAN-001',
            'cost_price' => 25000,
            'sale_price' => 55000,
            'stock' => 5,
            'min_stock' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to build sale payload.
     */
    protected function salePayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'customer_id' => null,
            'discount_percentage' => 0,
            'payment_method' => 'cash',
            'notes' => null,
            'items' => $items,
        ], $overrides);
    }

    public function test_admin_and_employee_can_view_sales_list(): void
    {
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUser->id,
            'invoice_number' => 'VTA-202608-0001',
            'sale_date' => now(),
            'total' => 35000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Admin can see sales list
        $adminResponse = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.index'));

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('VTA-202608-0001');

        // Employee can also see sales list
        $empResponse = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.index'));

        $empResponse->assertStatus(200);
        $empResponse->assertSee('VTA-202608-0001');
    }

    public function test_admin_and_employee_can_create_sale_and_stock_is_decremented(): void
    {
        $payload = $this->salePayload([
            ['product_id' => $this->productA->id, 'quantity' => 2, 'unit_price' => 35000],
            ['product_id' => $this->productB->id, 'quantity' => 1, 'unit_price' => 55000],
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Verify sale was created
        $this->assertDatabaseHas('sales', [
            'business_id' => $this->businessA->id,
            'user_id' => $this->employeeUser->id,
            'status' => 'completed',
            'total' => 125000.00, // (2*35000) + (1*55000) = 125000
        ]);

        // Verify detail lines
        $sale = Sale::withoutGlobalScopes()->where('business_id', $this->businessA->id)->first();
        $this->assertCount(2, $sale->details);

        // Verify stock was decremented
        $this->assertEquals(8, $this->productA->fresh()->stock);  // 10 - 2
        $this->assertEquals(4, $this->productB->fresh()->stock);  // 5 - 1

        // Verify inventory movements were created
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->productA->id,
            'sale_id' => $sale->id,
            'type' => 'exit',
            'quantity' => 2,
        ]);
    }

    public function test_sale_without_customer_is_allowed_mostrador(): void
    {
        $payload = $this->salePayload([
            ['product_id' => $this->productA->id, 'quantity' => 1, 'unit_price' => 35000],
        ], ['customer_id' => null]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();

        $sale = Sale::withoutGlobalScopes()->where('business_id', $this->businessA->id)->first();
        $this->assertNull($sale->customer_id);
    }

    public function test_sale_with_insufficient_stock_is_rejected(): void
    {
        $payload = $this->salePayload([
            ['product_id' => $this->productB->id, 'quantity' => 100, 'unit_price' => 55000],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items');

        // No sale should be created
        $this->assertDatabaseMissing('sales', [
            'business_id' => $this->businessA->id,
        ]);

        // Stock should remain unchanged
        $this->assertEquals(5, $this->productB->fresh()->stock);
    }

    public function test_sale_rejection_leaves_no_partial_records(): void
    {
        // Product A has 10 stock (enough for 2)
        // Product B has 5 stock (not enough for 50)
        $payload = $this->salePayload([
            ['product_id' => $this->productA->id, 'quantity' => 2, 'unit_price' => 35000],
            ['product_id' => $this->productB->id, 'quantity' => 50, 'unit_price' => 55000],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items');

        // No sale or sale_details should exist — full rollback
        $this->assertEquals(0, Sale::withoutGlobalScopes()->where('business_id', $this->businessA->id)->count());
        $this->assertEquals(0, SaleDetail::count());

        // Stock intact for both products
        $this->assertEquals(10, $this->productA->fresh()->stock);
        $this->assertEquals(5, $this->productB->fresh()->stock);
    }

    public function test_admin_can_cancel_sale_and_stock_is_restored(): void
    {
        // First create a sale
        $payload = $this->salePayload([
            ['product_id' => $this->productA->id, 'quantity' => 3, 'unit_price' => 35000],
        ]);

        $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), $payload);

        $sale = Sale::withoutGlobalScopes()->where('business_id', $this->businessA->id)->first();
        $this->assertEquals(7, $this->productA->fresh()->stock); // 10 - 3

        // Now cancel it
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('sales.destroy', $sale));

        $response->assertRedirect(route('sales.index'));

        // Sale should be cancelled
        $this->assertEquals('cancelled', $sale->fresh()->status);

        // Stock should be restored
        $this->assertEquals(10, $this->productA->fresh()->stock); // 7 + 3
    }

    public function test_employee_cannot_cancel_sale(): void
    {
        $sale = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUser->id,
            'invoice_number' => 'VTA-202608-0001',
            'sale_date' => now(),
            'total' => 35000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('sales.destroy', $sale));

        $response->assertStatus(403);
    }

    public function test_invoice_number_is_auto_generated(): void
    {
        $payload = $this->salePayload([
            ['product_id' => $this->productA->id, 'quantity' => 1, 'unit_price' => 35000],
        ]);

        $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), $payload);

        $sale = Sale::withoutGlobalScopes()->where('business_id', $this->businessA->id)->first();
        $this->assertNotNull($sale->invoice_number);
        $this->assertStringStartsWith('VTA-', $sale->invoice_number);
    }

    public function test_sales_are_isolated_between_businesses(): void
    {
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUser->id,
            'invoice_number' => 'VTA-A-0001',
            'sale_date' => now(),
            'total' => 10000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminUser->id,
            'invoice_number' => 'VTA-B-0001',
            'sale_date' => now(),
            'total' => 20000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.index'));

        $response->assertSee('VTA-A-0001');
        $response->assertDontSee('VTA-B-0001');
    }

    public function test_sale_detail_totals_are_computed_correctly(): void
    {
        $payload = $this->salePayload([
            ['product_id' => $this->productA->id, 'quantity' => 3, 'unit_price' => 35000],
            ['product_id' => $this->productB->id, 'quantity' => 2, 'unit_price' => 55000],
        ], ['discount_percentage' => 10]);

        $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), $payload);

        $sale = Sale::withoutGlobalScopes()->where('business_id', $this->businessA->id)->first();

        // subtotal = (3*35000) + (2*55000) = 105000 + 110000 = 215000
        $this->assertEquals(215000.00, (float) $sale->subtotal);
        // discount = 215000 * 10% = 21500
        $this->assertEquals(21500.00, (float) $sale->discount);
        $this->assertEquals(10.00, (float) $sale->discount_percentage);
        // total = 215000 - 21500 = 193500
        $this->assertEquals(193500.00, (float) $sale->total);

        // Line subtotals
        $details = $sale->details()->orderBy('id')->get();
        $this->assertEquals(105000.00, (float) $details[0]->subtotal); // 3 * 35000
        $this->assertEquals(110000.00, (float) $details[1]->subtotal); // 2 * 55000
    }

    public function test_product_search_api_returns_only_tenant_products(): void
    {
        // Create a product in business B
        $catB = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Otra Categoría',
        ]);

        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'category_id' => $catB->id,
            'name' => 'Producto Secreto B',
            'sku' => 'SEC-B-001',
            'sale_price' => 99999,
            'stock' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('api.products.search', ['q' => 'Producto']));

        $response->assertStatus(200);

        $json = $response->json();

        // Should not contain business B's product
        $names = array_column($json, 'name');
        $this->assertNotContains('Producto Secreto B', $names);
    }
}
