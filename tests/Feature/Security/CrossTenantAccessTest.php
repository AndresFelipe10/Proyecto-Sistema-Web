<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $tenantA;
    protected Business $tenantB;
    protected User $userA;
    protected Category $categoryB;
    protected Product $productB;
    protected Customer $customerB;
    protected Supplier $supplierB;
    protected Sale $saleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->tenantA = Business::create(['name' => 'Empresa A']);
        $this->tenantB = Business::create(['name' => 'Empresa B']);

        $this->userA = User::factory()->create();
        $this->tenantA->users()->attach($this->userA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Tenant B data created withoutGlobalScopes
        $this->categoryB = Category::withoutGlobalScopes()->create([
            'business_id' => $this->tenantB->id,
            'name' => 'Categoria Tenant B',
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'business_id' => $this->tenantB->id,
            'category_id' => $this->categoryB->id,
            'name' => 'Producto Tenant B',
            'sku' => 'SKU-TENANT-B',
            'cost_price' => 5000,
            'selling_price' => 8000,
            'stock' => 20,
            'min_stock' => 5,
        ]);

        $this->customerB = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->tenantB->id,
            'name' => 'Cliente Tenant B',
            'identification_number' => '12345678',
            'email' => 'clienteb@test.com',
        ]);

        $this->supplierB = Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->tenantB->id,
            'name' => 'Proveedor Tenant B',
            'contact_name' => 'Contacto B',
            'email' => 'supplierb@test.com',
        ]);

        $this->saleB = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->tenantB->id,
            'user_id' => $this->userA->id,
            'customer_id' => $this->customerB->id,
            'invoice_number' => 'V-TB-001',
            'subtotal' => 8000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 8000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'sale_date' => now(),
        ]);
    }

    public function test_cross_tenant_product_routes_blocked(): void
    {
        $session = ['current_business_id' => $this->tenantA->id];

        $this->actingAs($this->userA)->withSession($session)
            ->get("/products/{$this->productB->id}")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->get("/products/{$this->productB->id}/edit")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->put("/products/{$this->productB->id}", ['name' => 'Hacked'])
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->delete("/products/{$this->productB->id}")
            ->assertStatus(404);
    }

    public function test_cross_tenant_sale_routes_blocked(): void
    {
        $session = ['current_business_id' => $this->tenantA->id];

        $this->actingAs($this->userA)->withSession($session)
            ->get("/sales/{$this->saleB->id}")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->get("/sales/{$this->saleB->id}/print/invoice")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->get("/sales/{$this->saleB->id}/print/receipt")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->delete("/sales/{$this->saleB->id}")
            ->assertStatus(404);
    }

    public function test_cross_tenant_customer_routes_blocked(): void
    {
        $session = ['current_business_id' => $this->tenantA->id];

        $this->actingAs($this->userA)->withSession($session)
            ->get("/customers/{$this->customerB->id}")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->get("/customers/{$this->customerB->id}/edit")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->put("/customers/{$this->customerB->id}", ['name' => 'Hacked'])
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->delete("/customers/{$this->customerB->id}")
            ->assertStatus(404);
    }

    public function test_cross_tenant_supplier_routes_blocked(): void
    {
        $session = ['current_business_id' => $this->tenantA->id];

        $this->actingAs($this->userA)->withSession($session)
            ->get("/suppliers/{$this->supplierB->id}")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->get("/suppliers/{$this->supplierB->id}/edit")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->put("/suppliers/{$this->supplierB->id}", ['name' => 'Hacked'])
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->delete("/suppliers/{$this->supplierB->id}")
            ->assertStatus(404);
    }

    public function test_cross_tenant_category_routes_blocked(): void
    {
        $session = ['current_business_id' => $this->tenantA->id];

        $this->actingAs($this->userA)->withSession($session)
            ->get("/categories/{$this->categoryB->id}/edit")
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->put("/categories/{$this->categoryB->id}", ['name' => 'Hacked'])
            ->assertStatus(404);

        $this->actingAs($this->userA)->withSession($session)
            ->delete("/categories/{$this->categoryB->id}")
            ->assertStatus(404);
    }
}
