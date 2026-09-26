<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_sanitizes_formula_injection_characters(): void
    {
        $adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $business = Business::create(['name' => 'Test Business Formulas']);
        $admin = User::factory()->create();
        $business->users()->attach($admin->id, [
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        // Customer with malicious formula names
        $maliciousCustomer = Customer::create([
            'business_id' => $business->id,
            'name' => '=1+1;cmd|calc',
            'identification_number' => '+573001234567',
        ]);

        // Product with formula names
        $category = Category::create([
            'business_id' => $business->id,
            'name' => '-SUM(A1:A10)',
        ]);

        $maliciousProduct = Product::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name' => '=HYPERLINK("http://evil.com","Click")',
            'sku' => '@SECRET_SKU',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'stock' => 10,
            'min_stock' => 2,
        ]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'user_id' => $admin->id,
            'customer_id' => $maliciousCustomer->id,
            'invoice_number' => '+INV-001',
            'subtotal' => 2000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 2000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'sale_date' => now(),
        ]);

        $reportService = app(ReportService::class);

        // 1. Check Sales CSV Export
        $salesCsv = $reportService->exportSalesCsv($business->id);

        // Raw malicious values must NOT appear with raw leading =, +, -, @
        $this->assertStringNotContainsString('",=1+1;cmd|calc,"', $salesCsv);
        $this->assertStringNotContainsString('"+INV-001"', $salesCsv);

        // Escaped versions starting with single quote (') must be present
        $this->assertStringContainsString("'=1+1;cmd|calc", $salesCsv);
        $this->assertStringContainsString("'+INV-001", $salesCsv);

        // 2. Check Inventory CSV Export
        $inventoryCsv = $reportService->exportInventoryCsv($business->id);

        $this->assertStringNotContainsString('"=HYPERLINK(', $inventoryCsv);
        $this->assertStringNotContainsString('"@SECRET_SKU"', $inventoryCsv);
        $this->assertStringNotContainsString('"-SUM(', $inventoryCsv);

        $this->assertStringContainsString("'=HYPERLINK(", $inventoryCsv);
        $this->assertStringContainsString("'@SECRET_SKU", $inventoryCsv);
        $this->assertStringContainsString("'-SUM(", $inventoryCsv);
    }
}
