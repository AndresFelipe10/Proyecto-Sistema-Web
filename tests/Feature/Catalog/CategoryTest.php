<?php

namespace Tests\Feature\Catalog;

use App\Models\Business;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUser;
    protected User $employeeUser;

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

        $this->businessA = Business::create(['name' => 'Empresa A']);
        $this->businessB = Business::create(['name' => 'Empresa B']);

        $this->adminUser = User::factory()->create();
        $this->businessA->users()->attach($this->adminUser->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUser = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUser->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);
    }

    public function test_admin_and_employee_can_view_categories_list(): void
    {
        Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Calzado Deportivo',
        ]);

        $adminResponse = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('categories.index'));

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Calzado Deportivo');

        $empResponse = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('categories.index'));

        $empResponse->assertStatus(200);
        $empResponse->assertSee('Calzado Deportivo');
    }

    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('categories.store'), [
                'name' => 'Accesorios de Moda',
                'description' => 'Bolsos, cinturones y carteras',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'business_id' => $this->businessA->id,
            'name' => 'Accesorios de Moda',
        ]);
    }

    public function test_employee_cannot_create_category(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('categories.store'), [
                'name' => 'Categoría No Permitida',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Nombre Viejo',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('categories.update', $category), [
                'name' => 'Nombre Actualizado',
                'description' => 'Nueva descripción',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Nombre Actualizado',
        ]);
    }

    public function test_employee_cannot_update_category(): void
    {
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Original',
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('categories.update', $category), [
                'name' => 'Hack Attempt',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_category(): void
    {
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Para Eliminar',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_employee_cannot_delete_category(): void
    {
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'No Borrar',
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('categories.destroy', $category));

        $response->assertStatus(403);
    }

    public function test_categories_are_isolated_between_businesses(): void
    {
        $catA = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Categoría Exclusiva A',
        ]);

        $catB = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Categoría Exclusiva B',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('categories.index'));

        $response->assertSee('Categoría Exclusiva A');
        $response->assertDontSee('Categoría Exclusiva B');
    }
}
