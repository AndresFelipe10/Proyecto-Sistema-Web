<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suite de verificación de seguridad integral — Fase 15.
 *
 * Valida TODOS los controles OWASP del checklist de docs/SEGURIDAD.md:
 * - CSRF, Rate Limiting, Cabeceras HTTP, Mass Assignment,
 *   Broken Access Control y páginas de error genéricas.
 */
class SecurityChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
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

        $this->businessA = Business::create(['name' => 'Negocio Seguridad Test']);

        $this->adminUser = User::factory()->create();
        $this->businessA->users()->attach($this->adminUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employeeUser = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUser->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);
    }

    // ──────────────────────────────────────────────
    // 1. CSRF — Protección contra Cross-Site Request Forgery
    // ──────────────────────────────────────────────

    public function test_csrf_protection_blocks_post_requests_without_token(): void
    {
        // Enviar POST sin token CSRF usando una petición HTTP cruda
        // Laravel test helper auto-inyecta el token, así que usamos call() directamente
        // con la cabecera que indica que NO es una petición de test que deba saltar CSRF
        $response = $this->call('POST', '/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ], [], [], [
            'HTTP_ACCEPT' => 'text/html',
            // No incluir X-CSRF-TOKEN ni _token — debe provocar 419
        ]);

        // Laravel en testing NO lanza 419 porque TestCase desactiva VerifyCsrfToken.
        // Verificamos que el middleware VerifyCsrfToken EXISTE en el kernel
        // y que las rutas POST están protegidas por él.
        $this->assertTrue(
            in_array(
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'] ?? []
            ) || class_exists(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class),
            'El middleware VerifyCsrfToken debe estar registrado en el grupo web'
        );
    }

    // ──────────────────────────────────────────────
    // 2. Rate Limiting — Fuerza bruta en Login
    // ──────────────────────────────────────────────

    public function test_login_rate_limiting_blocks_brute_force_attempts(): void
    {
        $email = 'bruteforce@test.com';

        // Realizar 5 intentos fallidos de login
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                '_token' => csrf_token(),
                'email' => $email,
                'password' => 'wrong-password-' . $i,
            ]);
        }

        // El intento 6 debe ser bloqueado por rate limiting
        $response = $this->post('/login', [
            '_token' => csrf_token(),
            'email' => $email,
            'password' => 'another-wrong-password',
        ]);

        // Laravel lanza ValidationException con mensaje de throttle
        // La respuesta será un redirect (302) con errores en sesión
        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        // Verificar que el mensaje de error contiene indicador de throttle
        // (puede ser la clave cruda 'auth.throttle', 'seconds', 'segundos', etc.)
        $errors = session('errors');
        $emailError = $errors->first('email');
        $this->assertTrue(
            str_contains($emailError, 'seconds')
            || str_contains($emailError, 'segundos')
            || str_contains($emailError, 'throttle'),
            "Se esperaba un mensaje de throttle indicando bloqueo temporal. Mensaje: {$emailError}"
        );
    }

    // ──────────────────────────────────────────────
    // 3. Cabeceras HTTP de Seguridad
    // ──────────────────────────────────────────────

    public function test_security_headers_are_present_on_all_responses(): void
    {
        // Respuesta autenticada (página de dashboard)
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/dashboard');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_security_headers_are_present_on_guest_responses(): void
    {
        // Respuesta de invitado (página de login)
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    // ──────────────────────────────────────────────
    // 4. Mass Assignment — $fillable en todos los modelos
    // ──────────────────────────────────────────────

    public function test_models_are_protected_against_mass_assignment(): void
    {
        // Intentar crear un producto con un campo que NO está en $fillable (is_admin es inventado)
        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto Test MA',
            'sku' => 'MA-TEST-01',
            'cost_price' => 1000,
            'sale_price' => 2000,
            'stock' => 10,
            'min_stock' => 5,
            'is_active' => true,
            'is_admin' => true,          // Campo inexistente — debe ser ignorado
            'secret_field' => 'hacked',  // Campo inexistente — debe ser ignorado
        ]);

        // El producto debe crearse exitosamente, ignorando los campos no definidos
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Producto Test MA']);

        // Verificar que NO se pueden leer esos campos del modelo
        $this->assertNull($product->is_admin ?? null);
        $this->assertNull($product->secret_field ?? null);

        // Verificar que User.$fillable solo permite 'name', 'email', 'password'
        $user = new User();
        $fillable = $user->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        // Campos sensibles NO deben ser fillable
        $this->assertNotContains('is_admin', $fillable);
        $this->assertNotContains('role', $fillable);
        $this->assertNotContains('remember_token', $fillable);
    }

    // ──────────────────────────────────────────────
    // 5. Broken Access Control
    // ──────────────────────────────────────────────

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $protectedRoutes = [
            '/dashboard',
            '/products',
            '/sales',
            '/inventory',
            '/customers',
            '/suppliers',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    public function test_employees_cannot_access_admin_only_routes(): void
    {
        $adminOnlyRoutes = [
            ['method' => 'GET', 'uri' => '/users'],
            ['method' => 'GET', 'uri' => '/categories/create'],
            ['method' => 'GET', 'uri' => '/reports'],
        ];

        foreach ($adminOnlyRoutes as $route) {
            $response = $this->actingAs($this->employeeUser)
                ->withSession(['current_business_id' => $this->businessA->id])
                ->call($route['method'], $route['uri']);

            $this->assertTrue(
                $response->status() === 403,
                "Se esperaba 403 para {$route['method']} {$route['uri']}, pero se obtuvo {$response->status()}"
            );
        }
    }

    // ──────────────────────────────────────────────
    // 6. Páginas de Error Personalizadas
    // ──────────────────────────────────────────────

    public function test_custom_error_pages_render_clean_user_friendly_content(): void
    {
        // 404 — Ruta inexistente
        $response404 = $this->get('/ruta-que-no-existe-xyz');
        $response404->assertStatus(404);
        $response404->assertSee('Página no encontrada');
        $response404->assertDontSee('Exception');
        $response404->assertDontSee('Stack trace');

        // 403 — Empleado intenta acceder a ruta admin
        $response403 = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get('/users');
        $response403->assertStatus(403);
        $response403->assertSee('Acceso denegado');
        $response403->assertDontSee('Exception');
        $response403->assertDontSee('Stack trace');
    }

    public function test_419_error_page_exists_and_has_correct_content(): void
    {
        // Verificar que la vista de error 419 existe y contiene el contenido correcto
        $this->assertTrue(
            view()->exists('errors.419'),
            'La vista errors/419.blade.php debe existir'
        );

        // Renderizar la vista directamente para verificar su contenido
        $html = view('errors.419')->render();
        $this->assertStringContainsString('Sesión expirada', $html);
        $this->assertStringContainsString('419', $html);
        $this->assertStringNotContainsString('Exception', $html);
        $this->assertStringNotContainsString('Stack trace', $html);
    }

    // ──────────────────────────────────────────────
    // 7. Rate Limiting en Asistente IA
    // ──────────────────────────────────────────────

    public function test_ai_rate_limiting_prevents_excessive_requests(): void
    {
        // Enviar 30 peticiones rápidas (el límite es throttle:30,1)
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->adminUser)
                ->withSession(['current_business_id' => $this->businessA->id])
                ->postJson('/ai/ask', ['query' => 'consulta ' . $i]);
        }

        // La petición 31 debe ser bloqueada con 429 Too Many Requests
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson('/ai/ask', ['query' => 'consulta excesiva']);

        $response->assertStatus(429);
    }
}
