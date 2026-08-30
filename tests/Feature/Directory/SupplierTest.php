<?php

namespace Tests\Feature\Directory;

use App\Models\Business;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

        $this->businessA = Business::create(['name' => 'Negocio Cali A']);
        $this->businessB = Business::create(['name' => 'Negocio Cali B']);

        $this->adminUser = User::factory()->create();
        $this->businessA->users()->attach($this->adminUser->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUser = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUser->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);
    }

    public function test_admin_and_employee_can_view_suppliers_list_and_details(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Distribuciones del Valle SAS',
            'identification_number' => '900123987-1',
            'contact_name' => 'Andrés Morales',
            'phone' => '3187654321',
            'email' => 'ventas@valle.com',
            'is_active' => true,
        ]);

        $adminResponse = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('suppliers.index'));

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Distribuciones del Valle SAS');

        $empResponse = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('suppliers.show', $supplier));

        $empResponse->assertStatus(200);
        $empResponse->assertSee('Distribuciones del Valle SAS');
    }

    public function test_admin_can_create_supplier(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('suppliers.store'), [
                'name' => 'Insumos Industriales Cali',
                'identification_number' => '890123456-5',
                'contact_name' => 'Lucía Restrepo',
                'phone' => '3165554433',
                'email' => 'contacto@insumoscali.com',
                'address' => 'Zona Industrial Acopi, Yumbo',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'business_id' => $this->businessA->id,
            'name' => 'Insumos Industriales Cali',
            'identification_number' => '890123456-5',
        ]);
    }

    public function test_employee_cannot_create_supplier(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('suppliers.store'), [
                'name' => 'Proveedor No Autorizado',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_supplier(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Proveedor Antiguo',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('suppliers.update', $supplier), [
                'name' => 'Proveedor Renovado',
                'contact_name' => 'Nuevo Contacto',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Proveedor Renovado',
            'contact_name' => 'Nuevo Contacto',
        ]);
    }

    public function test_employee_cannot_update_supplier(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Proveedor Original',
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('suppliers.update', $supplier), [
                'name' => 'Hack Attempt',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_supplier(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Proveedor Para Eliminar',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('suppliers.destroy', $supplier));

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseMissing('suppliers', [
            'id' => $supplier->id,
        ]);
    }

    public function test_employee_cannot_delete_supplier(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Proveedor Seguro',
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('suppliers.destroy', $supplier));

        $response->assertStatus(403);
    }

    public function test_suppliers_are_isolated_between_businesses(): void
    {
        Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Proveedor Exclusivo Empresa A',
        ]);

        Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Proveedor Exclusivo Empresa B',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('suppliers.index'));

        $response->assertSee('Proveedor Exclusivo Empresa A');
        $response->assertDontSee('Proveedor Exclusivo Empresa B');
    }
}
