<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Crea tu cuenta');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Carlos Rodriguez',
            'email' => 'carlos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Carlos Rodriguez',
            'email' => 'carlos@example.com',
        ]);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_registration_fails_if_email_already_exists(): void
    {
        User::factory()->create([
            'email' => 'carlos@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'Otro Carlos',
            'email' => 'carlos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_registration_fails_if_password_confirmation_does_not_match(): void
    {
        $response = $this->post('/register', [
            'name' => 'Carlos Rodriguez',
            'email' => 'carlos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('password');
    }
}
