<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertSee('Recuperar acceso');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $user = User::factory()->create([
            'email' => 'andres@example.com',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'andres@example.com',
        ]);

        $response->assertSessionHas('status');
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'email' => 'andres@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->get('/reset-password/' . $token . '?email=andres@example.com');

        $response->assertStatus(200);
        $response->assertSee('Crea una nueva contraseña');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'andres@example.com',
            'password' => bcrypt('oldpassword123'),
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'andres@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }
}
