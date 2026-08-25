<?php

namespace Tests\Feature\Tenant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
            'description' => 'Administrador',
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
            'description' => 'Empleado',
        ]);
    }

    public function test_user_without_business_is_redirected_to_business_creation_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('businesses.create'));
        $response->assertSessionHas('info');
    }

    public function test_user_can_create_business_and_becomes_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/businesses', [
            'name' => 'Emprendimiento Valle',
            'nit' => '901234567-8',
            'phone' => '3009876543',
            'email' => 'info@valle.com',
            'address' => 'Carrera 1 # 10-20',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('businesses', [
            'name' => 'Emprendimiento Valle',
            'nit' => '901234567-8',
        ]);

        $business = Business::where('name', 'Emprendimiento Valle')->first();
        $this->assertTrue($user->businesses()->where('businesses.id', $business->id)->exists());

        $pivot = $user->businesses()->where('businesses.id', $business->id)->first()->pivot;
        $this->assertEquals($this->adminRole->id, $pivot->role_id);
        $this->assertEquals($business->id, session('current_business_id'));
    }

    public function test_global_scope_isolates_records_between_tenants(): void
    {
        // Setup Tenant A
        $userA = User::factory()->create();
        $businessA = Business::create(['name' => 'Empresa A']);
        $businessA->users()->attach($userA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        // Setup Tenant B
        $userB = User::factory()->create();
        $businessB = Business::create(['name' => 'Empresa B']);
        $businessB->users()->attach($userB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        // Create records directly for Tenant A
        $catA = Category::withoutGlobalScopes()->create(['business_id' => $businessA->id, 'name' => 'Cat A']);
        $prodA = Product::withoutGlobalScopes()->create(['business_id' => $businessA->id, 'name' => 'Prod A', 'sku' => 'SKU-A', 'sale_price' => 100]);
        $custA = Customer::withoutGlobalScopes()->create(['business_id' => $businessA->id, 'name' => 'Cust A']);
        $suppA = Supplier::withoutGlobalScopes()->create(['business_id' => $businessA->id, 'name' => 'Supp A']);
        $saleA = Sale::withoutGlobalScopes()->create(['business_id' => $businessA->id, 'user_id' => $userA->id, 'sale_date' => now(), 'total' => 100, 'payment_method' => 'cash', 'status' => 'completed']);
        $movA = InventoryMovement::withoutGlobalScopes()->create(['business_id' => $businessA->id, 'product_id' => $prodA->id, 'user_id' => $userA->id, 'type' => 'entry', 'quantity' => 10, 'previous_stock' => 0, 'new_stock' => 10, 'movement_date' => now()]);

        // Create records directly for Tenant B
        $catB = Category::withoutGlobalScopes()->create(['business_id' => $businessB->id, 'name' => 'Cat B']);
        $prodB = Product::withoutGlobalScopes()->create(['business_id' => $businessB->id, 'name' => 'Prod B', 'sku' => 'SKU-B', 'sale_price' => 200]);
        $custB = Customer::withoutGlobalScopes()->create(['business_id' => $businessB->id, 'name' => 'Cust B']);
        $suppB = Supplier::withoutGlobalScopes()->create(['business_id' => $businessB->id, 'name' => 'Supp B']);
        $saleB = Sale::withoutGlobalScopes()->create(['business_id' => $businessB->id, 'user_id' => $userB->id, 'sale_date' => now(), 'total' => 200, 'payment_method' => 'card', 'status' => 'completed']);
        $movB = InventoryMovement::withoutGlobalScopes()->create(['business_id' => $businessB->id, 'product_id' => $prodB->id, 'user_id' => $userB->id, 'type' => 'entry', 'quantity' => 20, 'previous_stock' => 0, 'new_stock' => 20, 'movement_date' => now()]);

        // Activate Tenant A in TenantManager
        app(TenantManager::class)->set($businessA);

        $this->assertEquals(1, Category::count());
        $this->assertEquals('Cat A', Category::first()->name);

        $this->assertEquals(1, Product::count());
        $this->assertEquals('Prod A', Product::first()->name);

        $this->assertEquals(1, Customer::count());
        $this->assertEquals('Cust A', Customer::first()->name);

        $this->assertEquals(1, Supplier::count());
        $this->assertEquals('Supp A', Supplier::first()->name);

        $this->assertEquals(1, Sale::count());
        $this->assertEquals(100.00, (float) Sale::sum('total'));

        $this->assertEquals(1, InventoryMovement::count());
        $this->assertEquals(10, InventoryMovement::first()->quantity);

        // Activate Tenant B in TenantManager
        app(TenantManager::class)->set($businessB);

        $this->assertEquals(1, Category::count());
        $this->assertEquals('Cat B', Category::first()->name);

        $this->assertEquals(1, Product::count());
        $this->assertEquals('Prod B', Product::first()->name);

        $this->assertEquals(1, Customer::count());
        $this->assertEquals('Cust B', Customer::first()->name);

        $this->assertEquals(1, Supplier::count());
        $this->assertEquals('Supp B', Supplier::first()->name);

        $this->assertEquals(1, Sale::count());
        $this->assertEquals(200.00, (float) Sale::sum('total'));

        $this->assertEquals(1, InventoryMovement::count());
        $this->assertEquals(20, InventoryMovement::first()->quantity);
    }

    public function test_creating_records_automatically_assigns_active_tenant_id(): void
    {
        $business = Business::create(['name' => 'Empresa Activa']);
        app(TenantManager::class)->set($business);

        $category = Category::create([
            'name' => 'Categoría Test',
        ]);

        $this->assertEquals($business->id, $category->business_id);
    }

    public function test_user_cannot_switch_to_unauthorized_business(): void
    {
        $userA = User::factory()->create();
        $businessA = Business::create(['name' => 'Empresa A']);
        $businessA->users()->attach($userA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $userB = User::factory()->create();
        $businessB = Business::create(['name' => 'Empresa B']);
        $businessB->users()->attach($userB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        // User A tries to switch to Business B
        $response = $this->actingAs($userA)
            ->withSession(['current_business_id' => $businessA->id])
            ->post(route('businesses.switch', $businessB));

        $response->assertStatus(403);
    }

    public function test_user_can_switch_between_their_own_businesses(): void
    {
        $user = User::factory()->create();
        $business1 = Business::create(['name' => 'Mi Primer Negocio']);
        $business2 = Business::create(['name' => 'Mi Segundo Negocio']);

        $business1->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);
        $business2->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business1->id])
            ->post(route('businesses.switch', $business2));

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals($business2->id, session('current_business_id'));
    }

    public function test_user_cannot_update_unauthorized_business(): void
    {
        $userA = User::factory()->create();
        $businessA = Business::create(['name' => 'Empresa A']);
        $businessA->users()->attach($userA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $userB = User::factory()->create();
        $businessB = Business::create(['name' => 'Empresa B']);
        $businessB->users()->attach($userB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($userA)
            ->withSession(['current_business_id' => $businessA->id])
            ->put(route('businesses.update', $businessB), [
                'name' => 'Hack Attempt Name',
            ]);

        $response->assertStatus(403);
    }
}
