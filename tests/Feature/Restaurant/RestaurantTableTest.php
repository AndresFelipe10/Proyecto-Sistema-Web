<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantTableTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurantA;
    protected Business $restaurantB;
    protected Business $retailBusiness;
    protected User $adminA;
    protected User $employeeA;
    protected User $adminB;
    protected User $retailUser;

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

        $this->restaurantA = Business::create([
            'name' => 'Fogón Valluno A',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantB = Business::create([
            'name' => 'Fogón Valluno B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailBusiness = Business::create([
            'name' => 'Ferretería Central',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create();
        $this->restaurantA->users()->attach($this->adminA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employeeA = User::factory()->create();
        $this->restaurantA->users()->attach($this->employeeA->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->adminB = User::factory()->create();
        $this->restaurantB->users()->attach($this->adminB->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->retailUser = User::factory()->create();
        $this->retailBusiness->users()->attach($this->retailUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_crud_tables_successfully(): void
    {
        $this->actingAs($this->adminA);

        // 1. Index
        $response = $this->get(route('restaurant.tables.index'));
        $response->assertOk();
        $response->assertSee('Salón y Mapa de Mesas');

        // 2. Create view
        $response = $this->get(route('restaurant.tables.create'));
        $response->assertOk();

        // 3. Store table
        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa 1',
            'capacity' => 4,
        ]);
        $response->assertRedirect(route('restaurant.tables.index'));

        $this->assertDatabaseHas('restaurant_tables', [
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $table = RestaurantTable::where('business_id', $this->restaurantA->id)->first();

        // 4. Edit view
        $response = $this->get(route('restaurant.tables.edit', $table));
        $response->assertOk();
        $response->assertSee('Mesa 1');

        // 5. Update
        $response = $this->put(route('restaurant.tables.update', $table), [
            'name' => 'Mesa Principal',
            'capacity' => 6,
            'is_active' => 1,
        ]);
        $response->assertRedirect(route('restaurant.tables.index'));

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'name' => 'Mesa Principal',
            'capacity' => 6,
        ]);

        // 6. Delete
        $response = $this->delete(route('restaurant.tables.destroy', $table));
        $response->assertRedirect(route('restaurant.tables.index'));

        $this->assertDatabaseMissing('restaurant_tables', [
            'id' => $table->id,
        ]);
    }

    public function test_cannot_create_duplicate_table_name_within_same_business_but_allowed_across_tenants(): void
    {
        RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa 10',
            'capacity' => 4,
            'status' => 'available',
        ]);

        // En restaurantA: debe fallar con validación
        $this->actingAs($this->adminA);
        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa 10',
            'capacity' => 4,
        ]);
        $response->assertSessionHasErrors('name');

        // En restaurantB: debe permitirse el mismo nombre sin conflicto
        $this->actingAs($this->adminB);
        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa 10',
            'capacity' => 2,
        ]);
        $response->assertRedirect(route('restaurant.tables.index'));

        $this->assertDatabaseHas('restaurant_tables', [
            'business_id' => $this->restaurantB->id,
            'name' => 'Mesa 10',
            'capacity' => 2,
        ]);
    }

    public function test_retail_business_receives_403_forbidden_on_salon_and_tables_routes(): void
    {
        $this->actingAs($this->retailUser);

        // Index
        $response = $this->get(route('restaurant.tables.index'));
        $response->assertForbidden();

        // Create
        $response = $this->get(route('restaurant.tables.create'));
        $response->assertForbidden();

        // Store
        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa Retail',
            'capacity' => 4,
        ]);
        $response->assertForbidden();
    }

    public function test_employee_can_view_tables_but_cannot_create_or_modify(): void
    {
        $table = RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa Empleado',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->actingAs($this->employeeA);

        // Empleado puede ver el salón
        $response = $this->get(route('restaurant.tables.index'));
        $response->assertOk();

        // Empleado no puede entrar a la creación
        $response = $this->get(route('restaurant.tables.create'));
        $response->assertForbidden();

        // Empleado no puede crear
        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa No Autorizada',
            'capacity' => 4,
        ]);
        $response->assertForbidden();

        // Empleado no puede editar
        $response = $this->get(route('restaurant.tables.edit', $table));
        $response->assertForbidden();

        // Empleado no puede eliminar
        $response = $this->delete(route('restaurant.tables.destroy', $table));
        $response->assertForbidden();
    }

    public function test_cross_tenant_table_isolation(): void
    {
        $tableB = RestaurantTable::create([
            'business_id' => $this->restaurantB->id,
            'name' => 'Mesa Confidencial B',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->actingAs($this->adminA);

        // Intentar editar mesa de otro tenant da 404 (gracias a TenantScope) o 403
        $response = $this->get(route('restaurant.tables.edit', $tableB));
        $this->assertTrue(in_array($response->status(), [404, 403]));

        $response = $this->put(route('restaurant.tables.update', $tableB), [
            'name' => 'Mesa Hackeada',
            'capacity' => 10,
        ]);
        $this->assertTrue(in_array($response->status(), [404, 403]));

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $tableB->id,
            'name' => 'Mesa Confidencial B',
        ]);
    }

    public function test_cannot_delete_occupied_table(): void
    {
        $table = RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa Ocupada',
            'capacity' => 4,
            'status' => 'occupied',
        ]);

        $this->actingAs($this->adminA);

        $response = $this->delete(route('restaurant.tables.destroy', $table));
        $response->assertRedirect(route('restaurant.tables.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'status' => 'occupied',
        ]);
    }

    public function test_can_create_table_with_only_name_and_defaults_capacity_to_4(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Terraza 5',
            // capacity omitido intencionalmente
        ]);

        $response->assertRedirect(route('restaurant.tables.index'));

        $this->assertDatabaseHas('restaurant_tables', [
            'business_id' => $this->restaurantA->id,
            'name' => 'Terraza 5',
            'capacity' => 4,
            'status' => 'available',
        ]);
    }
}
