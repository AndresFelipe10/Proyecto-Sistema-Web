<?php

namespace Tests\Feature\Sales;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePrintTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUser;
    protected User $otherUser;
    protected Sale $sale;
    protected Sale $saleNoCustomer;

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

        $this->businessA = Business::create([
            'name' => 'Negocio A Print',
            'nit' => '900123456-7',
            'phone' => '3001234567',
            'email' => 'negocio@test.com',
            'address' => 'Cali, Colombia',
        ]);
        $this->businessB = Business::create(['name' => 'Negocio B Print']);

        $this->adminUser = User::factory()->create();
        $this->businessA->users()->attach($this->adminUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create();
        $this->businessB->users()->attach($this->otherUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'General',
        ]);

        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'category_id' => $category->id,
            'name' => 'Producto Print Test',
            'sku' => 'PRT-001',
            'cost_price' => 10000,
            'sale_price' => 50000,
            'stock' => 20,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        // Sale WITH customer and discount
        $customer = \App\Models\Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Print Test',
            'is_active' => true,
        ]);

        $this->sale = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUser->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'VTA-202609-0001',
            'sale_date' => now(),
            'subtotal' => 100000,
            'discount' => 10000,
            'discount_percentage' => 10.00,
            'total' => 90000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SaleDetail::create([
            'sale_id' => $this->sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
        ]);

        // Sale WITHOUT customer (Consumidor Final)
        $this->saleNoCustomer = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUser->id,
            'customer_id' => null,
            'invoice_number' => 'VTA-202609-0002',
            'sale_date' => now(),
            'subtotal' => 50000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 50000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);

        SaleDetail::create([
            'sale_id' => $this->saleNoCustomer->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'subtotal' => 50000,
        ]);
    }

    // ── Invoice Print Tests ──

    public function test_authorized_user_can_access_print_invoice(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.invoice', $this->sale));

        $response->assertStatus(200);
        $response->assertSee('VTA-202609-0001');
        $response->assertSee('Negocio A Print');
        $response->assertSee('Producto Print Test');
        $response->assertSee('Cliente Print Test');
    }

    public function test_authorized_user_can_access_print_receipt(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.receipt', $this->sale));

        $response->assertStatus(200);
        $response->assertSee('VTA-202609-0001');
        $response->assertSee('Negocio A Print');
        $response->assertSee('Producto Print Test');
    }

    public function test_other_business_user_cannot_access_print_invoice(): void
    {
        $response = $this->actingAs($this->otherUser)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->get(route('sales.print.invoice', $this->sale));

        $response->assertNotFound();
    }

    public function test_other_business_user_cannot_access_print_receipt(): void
    {
        $response = $this->actingAs($this->otherUser)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->get(route('sales.print.receipt', $this->sale));

        $response->assertNotFound();
    }

    public function test_print_invoice_shows_consumidor_final_when_no_customer(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.invoice', $this->saleNoCustomer));

        $response->assertStatus(200);
        $response->assertSee('Consumidor Final');
        $response->assertDontSee('Venta al mostrador');
        $response->assertDontSee('Venta de Mostrador');
        $response->assertDontSee('Mostrador');
    }

    public function test_print_receipt_shows_consumidor_final_when_no_customer(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.receipt', $this->saleNoCustomer));

        $response->assertStatus(200);
        $response->assertSee('Consumidor Final');
        $response->assertDontSee('Venta al mostrador');
    }

    public function test_print_invoice_shows_discount_percentage_and_amount(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.invoice', $this->sale));

        $response->assertStatus(200);
        $response->assertSee('10.0%');
        $response->assertSee('10.000');
    }

    public function test_print_receipt_shows_discount_percentage_and_amount(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.receipt', $this->sale));

        $response->assertStatus(200);
        $response->assertSee('10.0%');
        $response->assertSee('10.000');
    }

    public function test_print_views_show_business_nit(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.invoice', $this->sale));

        $response->assertSee('900123456-7');
    }
}
