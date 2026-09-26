<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminA;
    protected User $userB;

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

        $this->businessA = Business::create(['name' => 'Business A Test']);
        $this->businessB = Business::create(['name' => 'Business B Test']);

        $this->adminA = User::factory()->create();
        $this->businessA->users()->attach($this->adminA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create();
        $this->businessB->users()->attach($this->userB->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);
    }

    public function test_cross_tenant_user_update_is_blocked_with_403(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put("/users/{$this->userB->id}", [
                'role_id' => $this->adminRole->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_sole_admin_self_demotion_is_rejected(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put("/users/{$this->adminA->id}", [
                'role_id' => $this->employeeRole->id,
            ]);

        $response->assertSessionHasErrors(['role_id' => 'No puedes remover el único administrador del negocio.']);

        // Verify still admin
        $currentRole = $this->businessA->users()->where('users.id', $this->adminA->id)->first()->pivot->role_id;
        $this->assertEquals($this->adminRole->id, $currentRole);
    }

    public function test_admin_demotion_allowed_when_another_active_admin_exists(): void
    {
        $secondAdmin = User::factory()->create();
        $this->businessA->users()->attach($secondAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put("/users/{$this->adminA->id}", [
                'role_id' => $this->employeeRole->id,
            ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('status');

        $currentRole = $this->businessA->users()->where('users.id', $this->adminA->id)->first()->pivot->role_id;
        $this->assertEquals($this->employeeRole->id, $currentRole);
    }
}
