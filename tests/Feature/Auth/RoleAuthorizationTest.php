<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $business;
    protected User $adminUser;
    protected User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
            'description' => 'Acceso y administración total',
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
            'description' => 'Operación de ventas e inventario',
        ]);

        $this->business = Business::create([
            'name' => 'Calzado Cali',
            'nit' => '900123456-1',
        ]);

        $this->adminUser = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@cali.com']);
        $this->business->users()->attach($this->adminUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employeeUser = User::factory()->create(['name' => 'Employee User', 'email' => 'empleado@cali.com']);
        $this->business->users()->attach($this->employeeUser->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_users_index(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee('Equipo de Trabajo');
        $response->assertSee('admin@cali.com');
        $response->assertSee('empleado@cali.com');
    }

    public function test_employee_cannot_view_users_index(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('users.index'));

        $response->assertStatus(403);
    }

    public function test_employee_cannot_view_user_invite_page(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('users.create'));

        $response->assertStatus(403);
    }

    public function test_employee_cannot_store_new_user(): void
    {
        $newUser = User::factory()->create(['email' => 'nuevo@cali.com']);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.store'), [
                'email' => $newUser->email,
                'role_id' => $this->employeeRole->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_employee_cannot_update_user_role(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->put(route('users.update', $this->adminUser), [
                'role_id' => $this->employeeRole->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_employee_cannot_toggle_user_active_status(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.toggleActive', $this->adminUser));

        $response->assertStatus(403);
    }

    public function test_admin_can_invite_existing_user_to_business(): void
    {
        $collaborator = User::factory()->create(['email' => 'colaborador@cali.com']);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.store'), [
                'email' => $collaborator->email,
                'role_id' => $this->employeeRole->id,
            ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $collaborator->id,
            'role_id' => $this->employeeRole->id,
            'is_active' => 1,
        ]);
    }

    public function test_admin_cannot_invite_already_attached_user(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.store'), [
                'email' => $this->employeeUser->email,
                'role_id' => $this->employeeRole->id,
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_admin_can_update_member_role(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->put(route('users.update', $this->employeeUser), [
                'role_id' => $this->adminRole->id,
            ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $this->employeeUser->id,
            'role_id' => $this->adminRole->id,
        ]);
    }

    public function test_admin_can_toggle_member_active_status(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.toggleActive', $this->employeeUser));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $this->employeeUser->id,
            'is_active' => 0,
        ]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.toggleActive', $this->adminUser));

        $response->assertStatus(403);
    }

    public function test_employee_can_access_dashboard_and_businesses(): void
    {
        $dashResponse = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('dashboard'));

        $dashResponse->assertStatus(200);

        $bizResponse = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('businesses.index'));

        $bizResponse->assertStatus(200);
    }
}
