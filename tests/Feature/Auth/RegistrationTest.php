<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_returns_404(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_post_registration_returns_404(): void
    {
        $response = $this->post('/register', [
            'name' => 'Carlos Rodriguez',
            'email' => 'carlos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'carlos@example.com',
        ]);
    }

    public function test_login_screen_does_not_contain_create_account_link(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('Crear cuenta');
        $response->assertDontSee('/register');
        $response->assertSee('¿Quieres PuntoStock para tu negocio?');
        $response->assertSee('Escríbenos');
    }
}
