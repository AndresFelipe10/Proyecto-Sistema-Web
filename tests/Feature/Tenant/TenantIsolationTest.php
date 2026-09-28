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

    public function test_user_without_active_membership_cannot_enter_and_session_is_terminated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Tu cuenta no está activa. Contacta a soporte.']);
        $this->assertGuest();
    }

    public function test_normal_user_cannot_create_business_returns_404(): void
    {
        $user = User::factory()->create();
        $business = Business::create(['name' => 'Empresa Original']);
        $business->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($user)->post('/businesses', [
            'name' => 'Emprendimiento No Autorizado',
        ]);

        $response->assertNotFound();
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

    public function test_business_switch_route_is_removed_returns_404(): void
    {
        $userA = User::factory()->create();
        $businessA = Business::create(['name' => 'Empresa A']);
        $businessA->users()->attach($userA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $businessB = Business::create(['name' => 'Empresa B']);

        $response = $this->actingAs($userA)
            ->post("/businesses/{$businessB->id}/switch");

        $response->assertNotFound();
    }

    public function test_user_can_only_have_one_membership_enforced_by_database_unique_constraint(): void
    {
        $user = User::factory()->create();
        $business1 = Business::create(['name' => 'Mi Primer Negocio']);
        $business2 = Business::create(['name' => 'Mi Segundo Negocio']);

        $business1->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $business2->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);
    }

    public function test_admin_can_update_own_business_via_my_business_route(): void
    {
        $user = User::factory()->create();
        $business = Business::create(['name' => 'Mi Negocio Real']);
        $business->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($user)
            ->put(route('businesses.update'), [
                'name' => 'Mi Negocio Real Actualizado',
                'nit' => '901234567-8',
            ]);

        $response->assertRedirect(route('businesses.edit'));
        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'name' => 'Mi Negocio Real Actualizado',
            'nit' => '901234567-8',
        ]);
    }
}
