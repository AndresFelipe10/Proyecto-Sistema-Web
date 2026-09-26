<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\SaleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleSequentialInvoiceRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $business;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->business = Business::create(['name' => 'Race Condition Business']);
        $this->user = User::factory()->create();
        $this->business->users()->attach($this->user->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'business_id' => $this->business->id,
            'name' => 'Race Cat',
        ]);

        $this->product = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $category->id,
            'name' => 'Stocked Product',
            'sku' => 'SKU-RACE-01',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'stock' => 100,
            'min_stock' => 5,
        ]);
    }

    public function test_unique_constraint_blocks_duplicate_invoice_number_for_same_business(): void
    {
        $prefix = 'VTA-' . now()->format('Ym') . '-';
        $invoiceNumber = $prefix . '0001';

        Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $this->user->id,
            'customer_id' => null,
            'invoice_number' => $invoiceNumber,
            'sale_date' => now(),
            'subtotal' => 2000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 2000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Second insert with the identical (business_id, invoice_number) must fail with QueryException
        $this->expectException(QueryException::class);

        Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $this->user->id,
            'customer_id' => null,
            'invoice_number' => $invoiceNumber,
            'sale_date' => now(),
            'subtotal' => 4000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 4000,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);
    }

    public function test_different_businesses_can_have_same_invoice_number(): void
    {
        $businessB = Business::create(['name' => 'Other Business']);
        $prefix = 'VTA-' . now()->format('Ym') . '-';
        $invoiceNumber = $prefix . '0001';

        $saleA = Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $this->user->id,
            'customer_id' => null,
            'invoice_number' => $invoiceNumber,
            'sale_date' => now(),
            'subtotal' => 2000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 2000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Same invoice number for a different tenant must succeed
        $saleB = Sale::create([
            'business_id' => $businessB->id,
            'user_id' => $this->user->id,
            'customer_id' => null,
            'invoice_number' => $invoiceNumber,
            'sale_date' => now(),
            'subtotal' => 3000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 3000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);

        $this->assertNotEquals($saleA->business_id, $saleB->business_id);
        $this->assertEquals($saleA->invoice_number, $saleB->invoice_number);
    }

    public function test_consecutive_sales_generate_monotonic_sequential_invoices(): void
    {
        $service = app(SaleService::class);

        $payload = [
            'customer_id' => null,
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'payment_method' => 'cash',
            'notes' => null,
            'discount_percentage' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 2000,
                ],
            ],
        ];

        $sale1 = $service->processSale($payload, $this->business->id, $this->user->id);
        $sale2 = $service->processSale($payload, $this->business->id, $this->user->id);
        $sale3 = $service->processSale($payload, $this->business->id, $this->user->id);

        $prefix = 'VTA-' . now()->format('Ym') . '-';
        $this->assertEquals($prefix . '0001', $sale1->invoice_number);
        $this->assertEquals($prefix . '0002', $sale2->invoice_number);
        $this->assertEquals($prefix . '0003', $sale3->invoice_number);
    }
}
