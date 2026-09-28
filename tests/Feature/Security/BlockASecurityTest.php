<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BlockASecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Administrador']
        );

        $this->employeeRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Empleado', 'description' => 'Empleado']
        );
    }

    /**
     * 1. Usuario normal: conmutar o crear negocio -> 404
     */
    public function test_normal_user_cannot_switch_or_create_business_returns_404(): void
    {
        $user = User::factory()->create();
        $businessA = Business::create(['name' => 'Negocio A']);
        $businessA->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $businessB = Business::create(['name' => 'Negocio B']);

        // Intentar crear negocio
        $responseCreate = $this->actingAs($user)->get('/businesses/create');
        $responseCreate->assertNotFound();

        $responseStore = $this->actingAs($user)->post('/businesses', ['name' => 'Hacker Business']);
        $responseStore->assertNotFound();

        // Intentar conmutar negocio
        $responseSwitch = $this->actingAs($user)->post("/businesses/{$businessB->id}/switch");
        $responseSwitch->assertNotFound();
    }

    /**
     * 2. /register responde 404 en GET y POST
     */
    public function test_register_route_returns_404(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@test.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@test.com']);
    }

    /**
     * 3. Usuario sin membresía activa: sesión cerrada y mensaje "Tu cuenta no está activa. Contacta a soporte."
     */
    public function test_user_without_active_membership_session_is_terminated_and_rejected(): void
    {
        $user = User::factory()->create();

        // Petición web
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Tu cuenta no está activa. Contacta a soporte.']);
        $this->assertGuest();

        // Petición API / JSON
        $responseJson = $this->actingAs($user)->getJson('/api/products/search');
        $responseJson->assertStatus(403);
        $responseJson->assertJson(['message' => 'Tu cuenta no está activa. Contacta a soporte.']);
    }

    /**
     * 4. Usuario con negocio suspendido (inactive): no puede entrar y la sesión se cierra
     */
    public function test_user_with_suspended_business_session_is_terminated(): void
    {
        $user = User::factory()->create();
        $business = Business::create(['name' => 'Negocio Suspendido']);
        $business->status = 'inactive';
        $business->is_active = false;
        $business->save();

        $business->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Tu cuenta no está activa. Contacta a soporte.']);
        $this->assertGuest();

        // Petición API / JSON
        $responseJson = $this->actingAs($user)->getJson('/api/products/search');
        $responseJson->assertStatus(403);
        $responseJson->assertJson(['message' => 'Tu cuenta no está activa. Contacta a soporte.']);
    }

    /**
     * 5. is_superadmin enviado en cualquier formulario (Equipo) se ignora
     */
    public function test_is_superadmin_submitted_in_team_form_is_ignored(): void
    {
        $admin = User::factory()->create();
        $business = Business::create(['name' => 'Negocio Principal']);
        $business->users()->attach($admin->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('users.store'), [
                'name' => 'Usuario Con Trampa',
                'email' => 'trampa@test.com',
                'password' => 'password123',
                'role_id' => $this->employeeRole->id,
                'is_superadmin' => true,
                'is_superadmin' => 1,
            ]);

        $response->assertRedirect(route('users.index'));

        $createdUser = User::where('email', 'trampa@test.com')->firstOrFail();
        $this->assertFalse((bool) $createdUser->is_superadmin);
    }

    /**
     * 6. Superadmin: acceso solo con el flag; usuario normal recibe 404 en /superadmin
     */
    public function test_superadmin_access_requires_flag_normal_user_receives_404(): void
    {
        $normalUser = User::factory()->create(['is_superadmin' => false]);
        $business = Business::create(['name' => 'Negocio Normal']);
        $business->users()->attach($normalUser->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        // Usuario normal recibe 404 estricto
        $this->actingAs($normalUser)->get('/superadmin')->assertNotFound();
        $this->actingAs($normalUser)->get('/superadmin/businesses')->assertNotFound();

        // Superadmin accede exitosamente
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($superadmin)->get('/superadmin')->assertStatus(200);
        $this->actingAs($superadmin)->get('/superadmin/businesses')->assertStatus(200);
    }

    /**
     * 7. Superadmin en rutas de negocio es redirigido a /superadmin y nunca ve datos de tenants
     */
    public function test_superadmin_in_business_routes_is_redirected_and_cannot_view_tenant_data(): void
    {
        $business = Business::create(['name' => 'Confidencial']);
        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Producto Secreto',
            'sku' => 'SEC-001',
            'cost_price' => 100,
            'sale_price' => 200,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $superadmin = User::factory()->create(['is_superadmin' => true]);

        // Rutas de negocio redirigen al panel de superadmin
        $response = $this->actingAs($superadmin)->get('/products');
        $response->assertRedirect(route('superadmin.dashboard'));

        $responseSales = $this->actingAs($superadmin)->get('/sales');
        $responseSales->assertRedirect(route('superadmin.dashboard'));

        $responseDashboard = $this->actingAs($superadmin)->get('/dashboard');
        $responseDashboard->assertRedirect(route('superadmin.dashboard'));

        // API de productos devuelve 403 para superadmin
        $responseApi = $this->actingAs($superadmin)->getJson('/api/products/search?q=SEC');
        $responseApi->assertStatus(403);
    }

    /**
     * 8. Crear negocio + admin por superadmin es transaccional (rollback si algo falla)
     */
    public function test_superadmin_create_business_and_admin_is_transactional(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        // Intento con correo duplicado -> debe fallar la validación y no crear el negocio
        User::factory()->create(['email' => 'existente@test.com']);

        $response = $this->actingAs($superadmin)->post(route('superadmin.businesses.store'), [
            'name' => 'Negocio Fallido',
            'admin_name' => 'Admin Fallido',
            'admin_email' => 'existente@test.com',
        ]);

        $response->assertSessionHasErrors(['admin_email']);
        $this->assertDatabaseMissing('businesses', ['name' => 'Negocio Fallido']);

        // Creación exitosa
        $responseOk = $this->actingAs($superadmin)->post(route('superadmin.businesses.store'), [
            'name' => 'Negocio Exitoso',
            'nit' => '900999888-1',
            'admin_name' => 'Admin Nuevo',
            'admin_email' => 'admin.nuevo@test.com',
        ]);

        $responseOk->assertRedirect(route('superadmin.businesses.index'));
        $this->assertDatabaseHas('businesses', ['name' => 'Negocio Exitoso']);
        $this->assertDatabaseHas('users', ['email' => 'admin.nuevo@test.com', 'must_change_password' => true]);
        $responseOk->assertSessionHas('generated_password');
    }

    /**
     * 9. must_change_password redirige y exige nueva contraseña distinta de la actual
     */
    public function test_must_change_password_forces_redirect_until_changed(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temporal1234'),
            'must_change_password' => true,
        ]);
        $business = Business::create(['name' => 'Negocio Test']);
        $business->users()->attach($user->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        // Redirige a cambiar contraseña
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect(route('password.change'));

        // No permite misma contraseña
        $responseSame = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'temporal1234',
            'password' => 'temporal1234',
            'password_confirmation' => 'temporal1234',
        ]);
        $responseSame->assertSessionHasErrors('password');
        $this->assertTrue((bool) $user->fresh()->must_change_password);

        // Contraseña actual errónea
        $responseWrongCurrent = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'erronea1234',
            'password' => 'nuevaPasswordSegura123',
            'password_confirmation' => 'nuevaPasswordSegura123',
        ]);
        $responseWrongCurrent->assertSessionHasErrors('current_password');
        $this->assertTrue((bool) $user->fresh()->must_change_password);

        // Cambio exitoso
        $responseOk = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'temporal1234',
            'password' => 'nuevaPasswordSegura123',
            'password_confirmation' => 'nuevaPasswordSegura123',
        ]);

        $responseOk->assertRedirect(route('dashboard'));
        $this->assertFalse((bool) $user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('nuevaPasswordSegura123', $user->fresh()->password));
    }

    /**
     * 10. Pantalla de login contiene WhatsApp y no contiene "Crear cuenta"
     */
    public function test_login_screen_contains_whatsapp_and_omits_create_account(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('Crear cuenta');
        $response->assertDontSee('/register');
        $response->assertSee('¿Quieres PuntoStock para tu negocio?');
        $response->assertSee('bi-whatsapp', false);
        $response->assertSee('https://wa.me/' . config('app.support_whatsapp'), false);
    }

    /**
     * 11. "Equipo" no puede adjuntar usuarios de otros negocios ni responde distinto según dónde exista el correo
     */
    public function test_team_module_cannot_attach_existing_users_and_gives_generic_error(): void
    {
        $admin = User::factory()->create();
        $businessA = Business::create(['name' => 'Negocio A']);
        $businessA->users()->attach($admin->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        // Usuario que pertenece al Negocio B
        $otherUser = User::factory()->create(['email' => 'otro@negocio.com']);
        $businessB = Business::create(['name' => 'Negocio B']);
        $businessB->users()->attach($otherUser->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->withSession(['current_business_id' => $businessA->id])
            ->post(route('users.store'), [
                'name' => 'Intento Duplicado',
                'email' => 'otro@negocio.com',
                'password' => 'password123',
                'role_id' => $this->employeeRole->id,
            ]);

        $response->assertSessionHasErrors(['email' => 'Ese correo no está disponible.']);

        // El usuario de Negocio B no fue adjuntado a Negocio A
        $this->assertFalse($businessA->users()->where('users.id', $otherUser->id)->exists());
    }

    /**
     * 12. Comando superadmin:create funciona con contraseña oculta y rechaza emails con membresías
     */
    public function test_superadmin_create_command_validates_and_creates_user(): void
    {
        // Caso 1: Correo de usuario con membresías -> rechazado
        $normalUser = User::factory()->create(['email' => 'socio@negocio.com']);
        $business = Business::create(['name' => 'Negocio Socio']);
        $business->users()->attach($normalUser->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->artisan('superadmin:create socio@negocio.com')
            ->expectsOutputToContain('ya pertenece a un usuario vinculado a un emprendimiento')
            ->assertFailed();

        // Caso 2: Nuevo correo -> crea superadmin exitosamente
        $this->artisan('superadmin:create super@puntostock.com --name="Plataforma Superadmin"')
            ->expectsQuestion('Ingresa la contraseña del superadministrador (mínimo 12 caracteres):', 'superPassword1234')
            ->expectsQuestion('Confirma la contraseña:', 'superPassword1234')
            ->expectsOutputToContain('creado exitosamente')
            ->assertSuccessful();

        $superadmin = User::where('email', 'super@puntostock.com')->firstOrFail();
        $this->assertTrue((bool) $superadmin->is_superadmin);
        $this->assertTrue(Hash::check('superPassword1234', $superadmin->password));
    }
}
