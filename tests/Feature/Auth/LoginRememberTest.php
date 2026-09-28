<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRememberTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithBusiness(string $email, string $password = 'secret123'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt($password),
        ]);

        $adminRole = \App\Models\Role::firstOrCreate(
            ['slug' => \App\Models\Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Acceso y administración total']
        );

        $business = \App\Models\Business::create([
            'name' => 'Negocio Test',
            'status' => 'active',
        ]);
        $business->users()->attach($user->id, [
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        return $user;
    }

    public function test_login_with_remember_value_one_succeeds_without_validation_error(): void
    {
        $user = $this->createUserWithBusiness('juan@example.com');

        $response = $this->post('/login', [
            'email' => 'juan@example.com',
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_with_remember_browser_default_on_succeeds_without_validation_error(): void
    {
        $user = $this->createUserWithBusiness('maria@example.com');

        $response = $this->post('/login', [
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'remember' => 'on',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_with_remember_boolean_true_succeeds_without_validation_error(): void
    {
        $user = $this->createUserWithBusiness('carlos@example.com');

        $response = $this->post('/login', [
            'email' => 'carlos@example.com',
            'password' => 'secret123',
            'remember' => true,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_without_remember_parameter_succeeds(): void
    {
        $user = $this->createUserWithBusiness('ana@example.com');

        $response = $this->post('/login', [
            'email' => 'ana@example.com',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_failed_login_with_remember_on_shows_auth_error_not_validation_boolean(): void
    {
        $user = User::factory()->create([
            'email' => 'pedro@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'pedro@example.com',
            'password' => 'wrong-password',
            'remember' => 'on',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email']);
        $response->assertSessionDoesntHaveErrors(['remember']);
    }
}
