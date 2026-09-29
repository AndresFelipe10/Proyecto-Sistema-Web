<?php

namespace Tests\Feature\Reports;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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

        $this->businessA = Business::create(['name' => 'Emprendimiento A Cali']);
        $this->businessB = Business::create(['name' => 'Emprendimiento B Cali']);

        $this->adminUserA = User::factory()->create();
        $this->businessA->users()->attach($this->adminUserA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUserA = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUserA->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->adminUserB = User::factory()->create();
        $this->businessB->users()->attach($this->adminUserB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);
    }

    public function test_admin_can_access_reports_hub_and_sub_reports(): void
    {
        $routes = ['/reports', '/reports/sales', '/reports/inventory', '/reports/top-products'];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->adminUserA)
                ->withSession(['current_business_id' => $this->businessA->id])
                ->get($route);

            $response->assertStatus(200);
        }
    }

    public function test_employee_cannot_access_reports_gets_403(): void
    {
        $routes = ['/reports', '/reports/sales', '/reports/inventory', '/reports/top-products'];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->employeeUserA)
                ->withSession(['current_business_id' => $this->businessA->id])
                ->get($route);

            $response->assertStatus(403);
        }
    }

    public function test_sales_report_respects_tenant_isolation(): void
    {
        // Venta Tenant A: $200.000
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-A-001',
            'sale_date' => Carbon::today(),
            'subtotal' => 200000,
            'discount' => 0,
            'total' => 200000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Venta Tenant B: $950.000
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminUserB->id,
            'invoice_number' => 'VTA-B-001',
            'sale_date' => Carbon::today(),
            'subtotal' => 950000,
            'discount' => 0,
            'total' => 950000,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/reports/sales');

        $response->assertStatus(200);
        $response->assertSee('VTA-A-001');
        $response->assertDontSee('VTA-B-001');
        $response->assertViewHas('total_revenue', 200000.0);
        $response->assertViewHas('sales_count', 1);
    }

    public function test_sales_report_filters_by_date_range_and_payment_method(): void
    {
        // Venta 1: Hoy en efectivo ($60.000)
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-FILT-01',
            'sale_date' => Carbon::today(),
            'subtotal' => 60000,
            'discount' => 0,
            'total' => 60000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Venta 2: Hoy con tarjeta ($100.000)
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-FILT-02',
            'sale_date' => Carbon::today(),
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);

        // Filtrar solo por efectivo
        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/reports/sales?payment_method=cash');

        $response->assertStatus(200);
        $response->assertSee('VTA-FILT-01');
        $response->assertDontSee('VTA-FILT-02');
        $response->assertViewHas('total_revenue', 60000.0);
    }

    public function test_inventory_valuation_report_respects_tenant_isolation(): void
    {
        // Producto Tenant A: 10 unidades, costo $5.000, venta $10.000 (Val costo $50.000, venta $100.000)
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Café Tenant A',
            'sku' => 'VAL-A-01',
            'cost_price' => 5000,
            'sale_price' => 10000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        // Producto Tenant B: 50 unidades, costo $20.000, venta $40.000 (Val costo $1.000.000)
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Café Tenant B',
            'sku' => 'VAL-B-01',
            'cost_price' => 20000,
            'sale_price' => 40000,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/reports/inventory');

        $response->assertStatus(200);
        $response->assertSee('Café Tenant A');
        $response->assertDontSee('Café Tenant B');
        $response->assertViewHas('total_units', 10);
        $response->assertViewHas('total_cost_valuation', 50000.0);
        $response->assertViewHas('total_retail_valuation', 100000.0);
        $response->assertViewHas('total_potential_profit', 50000.0);
        $response->assertViewHas('potential_margin_percent', 50.0);
    }

    public function test_top_products_report_respects_tenant_isolation_and_calculates_profitability(): void
    {
        $prodA = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto Rentable A',
            'sku' => 'RENT-A',
            'cost_price' => 4000,
            'sale_price' => 10000,
            'stock' => 20,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $prodB = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Producto Tenant B',
            'sku' => 'RENT-B',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        // Venta Tenant A: 5 unidades de prodA (Revenue: 50.000, COGS: 20.000, Profit: 30.000)
        $saleA = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-TOP-A',
            'sale_date' => Carbon::now(),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SaleDetail::create([
            'sale_id' => $saleA->id,
            'product_id' => $prodA->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'subtotal' => 50000,
        ]);

        // Venta Tenant B: 20 unidades de prodB
        $saleB = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminUserB->id,
            'invoice_number' => 'VTA-TOP-B',
            'sale_date' => Carbon::now(),
            'subtotal' => 400000,
            'discount' => 0,
            'total' => 400000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SaleDetail::create([
            'sale_id' => $saleB->id,
            'product_id' => $prodB->id,
            'quantity' => 20,
            'unit_price' => 20000,
            'subtotal' => 400000,
        ]);

        $response = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/reports/top-products');

        $response->assertStatus(200);
        $response->assertSee('Producto Rentable A');
        $response->assertDontSee('Producto Tenant B');
        $response->assertViewHas('total_items_sold', 5);
        $response->assertViewHas('total_revenue', 50000.0);
        $response->assertViewHas('total_cogs', 20000.0);
        $response->assertViewHas('total_gross_profit', 30000.0);
    }

    public function test_sales_and_inventory_reports_export_valid_csv_with_tenant_isolation(): void
    {
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'VTA-CSV-A',
            'sale_date' => Carbon::today(),
            'subtotal' => 80000,
            'discount' => 0,
            'total' => 80000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminUserB->id,
            'invoice_number' => 'VTA-CSV-B',
            'sale_date' => Carbon::today(),
            'subtotal' => 990000,
            'discount' => 0,
            'total' => 990000,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);

        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto CSV A',
            'sku' => 'CSV-SKU-A',
            'cost_price' => 1000,
            'sale_price' => 2000,
            'stock' => 5,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Producto CSV B',
            'sku' => 'CSV-SKU-B',
            'cost_price' => 5000,
            'sale_price' => 10000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        // 1. Exportación CSV Ventas
        $salesCsvResponse = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/reports/sales?export=csv');

        $salesCsvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $salesCsvResponse->headers->get('Content-Type'));
        $contentSales = $salesCsvResponse->getContent();
        $this->assertStringContainsString('VTA-CSV-A', $contentSales);
        $this->assertStringNotContainsString('VTA-CSV-B', $contentSales);

        // 2. Exportación CSV Inventario
        $invCsvResponse = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/reports/inventory?export=csv');

        $invCsvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $invCsvResponse->headers->get('Content-Type'));
        $contentInv = $invCsvResponse->getContent();
        $this->assertStringContainsString('CSV-SKU-A', $contentInv);
        $this->assertStringNotContainsString('CSV-SKU-B', $contentInv);
    }

    public function test_admin_and_employee_can_access_cash_register_report(): void
    {
        // Admin access
        $adminResponse = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/cash-register');

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Cuadre de Caja');

        // Employee access (as per user clarification: accessible to both admins and employees)
        $employeeResponse = $this->actingAs($this->employeeUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/cash-register');

        $employeeResponse->assertStatus(200);
        $employeeResponse->assertSee('Cuadre de Caja');
    }

    public function test_cash_register_report_isolates_by_tenant(): void
    {
        $today = Carbon::today()->setTime(10, 0);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'CUADRE-TENANT-A',
            'sale_date' => $today,
            'subtotal' => 50000,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminUserB->id,
            'invoice_number' => 'CUADRE-TENANT-B',
            'sale_date' => $today,
            'subtotal' => 80000,
            'total' => 80000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->employeeUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/cash-register?date=' . $today->format('Y-m-d'));

        $response->assertStatus(200);
        $response->assertSee('CUADRE-TENANT-A');
        $response->assertDontSee('CUADRE-TENANT-B');
    }

    public function test_cash_register_discriminates_by_payment_method_daily_and_monthly(): void
    {
        $date = Carbon::today()->setTime(14, 0);

        // Venta 1: Efectivo con vuelto
        $sale1 = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->employeeUserA->id,
            'invoice_number' => 'CUADRE-VTA-1',
            'sale_date' => $date,
            'subtotal' => 30000,
            'total' => 30000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        \App\Models\SalePayment::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'sale_id' => $sale1->id,
            'method' => 'cash',
            'amount' => 30000,
            'cash_received' => 50000,
            'change_given' => 20000,
        ]);

        // Venta 2: Nequi / Transferencia
        $sale2 = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->employeeUserA->id,
            'invoice_number' => 'CUADRE-VTA-2',
            'sale_date' => $date,
            'subtotal' => 25000,
            'total' => 25000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);
        \App\Models\SalePayment::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'sale_id' => $sale2->id,
            'method' => 'transfer',
            'amount' => 25000,
            'reference' => 'NEQUI-9988',
        ]);

        // Cuadre diario
        $dailyResponse = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/cash-register?period_type=daily&date=' . $date->format('Y-m-d'));

        $dailyResponse->assertStatus(200);
        $dailyResponse->assertSee('$55.000'); // Total recaudado
        $dailyResponse->assertSee('$30.000'); // Efectivo
        $dailyResponse->assertSee('$25.000'); // Transferencia

        // Cuadre mensual
        $monthlyResponse = $this->actingAs($this->adminUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/cash-register?period_type=monthly&month=' . $date->format('Y-m'));

        $monthlyResponse->assertStatus(200);
        $monthlyResponse->assertSee('$55.000');
    }

    public function test_cash_register_csv_export_works_and_is_tenant_isolated(): void
    {
        $date = Carbon::today()->setTime(11, 0);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'CSV-CUADRE-A',
            'sale_date' => $date,
            'subtotal' => 40000,
            'total' => 40000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminUserB->id,
            'invoice_number' => 'CSV-CUADRE-B',
            'sale_date' => $date,
            'subtotal' => 90000,
            'total' => 90000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $csvResponse = $this->actingAs($this->employeeUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/cash-register?export=csv&date=' . $date->format('Y-m-d'));

        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('Content-Type'));
        $content = $csvResponse->getContent();
        $this->assertStringContainsString('CSV-CUADRE-A', $content);
        $this->assertStringNotContainsString('CSV-CUADRE-B', $content);
    }

    public function test_sales_print_receipt_shows_notes_if_present(): void
    {
        $sale = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUserA->id,
            'invoice_number' => 'REC-NOTES-001',
            'sale_date' => Carbon::now(),
            'subtotal' => 15000,
            'total' => 15000,
            'payment_method' => 'cash',
            'notes' => 'Cliente solicita empaque para regalo',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->employeeUserA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/sales/' . $sale->id . '/print/receipt');

        $response->assertStatus(200);
        $response->assertSee('Cliente solicita empaque para regalo');
    }
}
