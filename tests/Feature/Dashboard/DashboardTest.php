<?php

namespace Tests\Feature\Dashboard;

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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUserA;
    protected User $employeeUserA;
    protected User $adminUserB;

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

        $this->businessA = Business::create(['name' => 'Negocio A Cali']);
        $this->businessB = Business::create(['name' => 'Negocio B Cali']);

        $this->adminUserA = User::factory()->create();
        $this->businessA->users()->attach($this->adminUserA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUserA = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUserA->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->adminUserB = User::factory()->create();
        $this->businessB->users()->attach($this->adminUserB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);
    }

    public function test_authenticated_user_with_tenant_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Negocio A Cali');
        $response->assertSee('Ventas Hoy');
        $response->assertSee('Ventas del Mes');
        $response->assertSee('Stock Crítico');
        $response->assertSee('Más Vendidos');

        $employeeResponse = $this->actingAs($this->employeeUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/dashboard');

        $employeeResponse->assertStatus(200);
        $employeeResponse->assertSee('Negocio A Cali');
    }

    public function test_user_without_tenant_is_redirected(): void
    {
        $userWithoutTenant = User::factory()->create();

        $response = $this->actingAs($userWithoutTenant)->get('/dashboard');

        $response->assertRedirect('/businesses/create');
    }

    public function test_dashboard_metrics_reflect_only_active_tenant_data(): void
    {
        // Tenant A: Venta completada hoy de $150.000
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-202609-0001',
            'sale_date' => Carbon::today(),
            'subtotal' => 150000,
            'discount' => 0,
            'total' => 150000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Tenant B: Venta completada hoy de $850.000
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminUserB->id,
            'invoice_number' => 'VTA-202609-0002',
            'sale_date' => Carbon::today(),
            'subtotal' => 850000,
            'discount' => 0,
            'total' => 850000,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('today_sales_total', 150000.0);
        $response->assertViewHas('today_sales_count', 1);
        $response->assertSee('$150.000');
        $response->assertDontSee('$850.000');
    }

    public function test_dashboard_low_stock_alerts_detect_critical_inventory(): void
    {
        // Tenant A: Producto 1 con stock crítico (stock 2 <= min_stock 5)
        $lowStockProduct = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Café Gourmet A',
            'sku' => 'CFE-001',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 2,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        // Tenant A: Producto 2 agotado (stock 0)
        $outOfStockProduct = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Té Verde A',
            'sku' => 'TE-002',
            'cost_price' => 5000,
            'sale_price' => 12000,
            'stock' => 0,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        // Tenant A: Producto 3 con stock normal (stock 50 > min_stock 10)
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Galletas A',
            'sku' => 'GAL-003',
            'cost_price' => 2000,
            'sale_price' => 4000,
            'stock' => 50,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        // Tenant B: Producto en stock crítico de otro tenant
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Producto Negocio B',
            'sku' => 'PROD-B-999',
            'cost_price' => 1000,
            'sale_price' => 2000,
            'stock' => 1,
            'min_stock' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('low_stock_count', 2);
        $response->assertViewHas('out_of_stock_count', 1);
        $response->assertSee('Café Gourmet A');
        $response->assertSee('Té Verde A');
        $response->assertDontSee('Producto Negocio B');
    }

    public function test_top_selling_products_are_calculated_accurately(): void
    {
        $prodA = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto Estrella A',
            'sku' => 'EST-001',
            'cost_price' => 1000,
            'sale_price' => 5000,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $prodB = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto Secundario B',
            'sku' => 'SEC-002',
            'cost_price' => 2000,
            'sale_price' => 8000,
            'stock' => 30,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $sale = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-202609-0010',
            'sale_date' => Carbon::now(),
            'subtotal' => 66000,
            'discount' => 0,
            'total' => 66000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // ProdA: 10 unidades vendidas
        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $prodA->id,
            'quantity' => 10,
            'unit_price' => 5000,
            'subtotal' => 50000,
        ]);

        // ProdB: 2 unidades vendidas
        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $prodB->id,
            'quantity' => 2,
            'unit_price' => 8000,
            'subtotal' => 16000,
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $topProducts = $response->viewData('top_products');
        $this->assertCount(2, $topProducts);
        $this->assertEquals($prodA->id, $topProducts[0]->id);
        $this->assertEquals(10, $topProducts[0]->total_sold_quantity);
        $this->assertEquals($prodB->id, $topProducts[1]->id);
        $this->assertEquals(2, $topProducts[1]->total_sold_quantity);
    }

    public function test_cancelled_sales_are_excluded_from_financial_kpis(): void
    {
        // Venta completada: $40.000
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-202609-0020',
            'sale_date' => Carbon::today(),
            'subtotal' => 40000,
            'discount' => 0,
            'total' => 40000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Venta anulada: $120.000
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-202609-0021',
            'sale_date' => Carbon::today(),
            'subtotal' => 120000,
            'discount' => 0,
            'total' => 120000,
            'payment_method' => 'transfer',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('today_sales_total', 40000.0);
        $response->assertViewHas('today_sales_count', 1);
        $response->assertSee('$40.000');
        $response->assertDontSee('$160.000');
    }
}
