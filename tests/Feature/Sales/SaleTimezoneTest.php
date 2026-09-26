<?php

namespace Tests\Feature\Sales;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $business;
    protected User $user;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->business = Business::create(['name' => 'Comercio Cali']);

        $this->user = User::factory()->create();
        $this->business->users()->attach($this->user->id, [
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
            'name' => 'Café de Origen Valle',
            'sku' => 'CAFE-01',
            'cost_price' => 10000,
            'selling_price' => 18000,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $this->customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->business->id,
            'name' => 'Cliente Frecuente Cali',
            'phone' => '3001234567',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset mocked time
        parent::tearDown();
    }

    public function test_sales_pos_form_defaults_to_america_bogota_time(): void
    {
        // Fix test time to 2026-09-19 14:35:00 in America/Bogota
        $knownTime = Carbon::create(2026, 9, 19, 14, 35, 0, 'America/Bogota');
        Carbon::setTestNow($knownTime);

        $response = $this->actingAs($this->user)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('sales.create'));

        $response->assertStatus(200);
        // Form field should default to 2026-09-19T14:35 (not shifted by UTC)
        $response->assertSee('value="2026-09-19T14:35"', false);
    }

    public function test_sale_creation_records_and_displays_exact_colombia_time(): void
    {
        $knownTime = Carbon::create(2026, 9, 19, 14, 35, 0, 'America/Bogota');
        Carbon::setTestNow($knownTime);

        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => $knownTime->format('Y-m-d H:i:s'),
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 18000,
                ],
            ],
            'discount_percentage' => 0,
        ];

        $response = $this->actingAs($this->user)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('sales.store'), $payload);

        $sale = Sale::latest('id')->first();
        $this->assertNotNull($sale);
        $response->assertRedirect(route('sales.show', $sale));

        // Verify sale_date in America/Bogota corresponds to the exact test time
        $saleDate = $sale->sale_date->setTimezone('America/Bogota');
        $this->assertEquals('2026-09-19 14:35:00', $saleDate->format('Y-m-d H:i:s'));
        $this->assertEquals(14, $saleDate->hour);
        $this->assertEquals(35, $saleDate->minute);

        // View invoice/show screen and verify formatted time displays 14:35
        $viewResponse = $this->actingAs($this->user)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('sales.show', $sale));

        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('19/09/2026 14:35');
    }
}
