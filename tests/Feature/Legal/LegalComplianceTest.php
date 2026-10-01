<?php

namespace Tests\Feature\Legal;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $business;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Administrador del negocio']
        );

        $this->business = Business::create(['name' => 'Comercio Cali Legal']);
        $this->adminUser = User::factory()->create();
        $this->business->users()->attach($this->adminUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Las rutas públicas legales son accesibles sin autenticación (HTTP 200).
     */
    public function test_public_legal_routes_are_accessible_without_authentication(): void
    {
        $termsResponse = $this->get('/terminos-y-condiciones');
        $termsResponse->assertStatus(200);
        $termsResponse->assertSee('Términos y Condiciones del Servicio');
        $termsResponse->assertSee('Software como Servicio');
        $termsResponse->assertSee('Ley 527 de 1999');
        $termsResponse->assertSee('DIAN');

        $privacyResponse = $this->get('/politica-de-privacidad');
        $privacyResponse->assertStatus(200);
        $privacyResponse->assertSee('Política de Privacidad y Tratamiento de Datos');
        $privacyResponse->assertSee('Ley 1581 de 2012');
        $privacyResponse->assertSee('laravel_session');
        $privacyResponse->assertSee('XSRF-TOKEN');
        $privacyResponse->assertSee('Encargado del Tratamiento');
    }

    /**
     * 2. Usuarios autenticados también pueden consultar los documentos legales.
     */
    public function test_authenticated_users_can_access_legal_routes(): void
    {
        $this->actingAs($this->adminUser);

        $termsResponse = $this->get(route('legal.terms'));
        $termsResponse->assertStatus(200);

        $privacyResponse = $this->get(route('legal.privacy'));
        $privacyResponse->assertStatus(200);
    }

    /**
     * 3. El cambio de contraseña obligatorio rechaza solicitudes sin aceptación de términos.
     */
    public function test_mandatory_password_change_requires_accepting_terms(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('claveTemporal123'),
            'must_change_password' => true,
            'terms_accepted_at' => null,
            'terms_accepted_ip' => null,
        ]);
        $this->business->users()->attach($user->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasAcceptedTerms());

        // Intento 1: omitiendo accept_terms por completo
        $responseWithoutCheckbox = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'claveTemporal123',
            'password' => 'nuevaClaveSegura2026',
            'password_confirmation' => 'nuevaClaveSegura2026',
        ]);

        $responseWithoutCheckbox->assertSessionHasErrors('accept_terms');
        $this->assertTrue((bool) $user->fresh()->must_change_password);
        $this->assertNull($user->fresh()->terms_accepted_at);
        $this->assertFalse($user->fresh()->hasAcceptedTerms());

        // Intento 2: enviando accept_terms con valor falso/cero
        $responseWithZero = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'claveTemporal123',
            'password' => 'nuevaClaveSegura2026',
            'password_confirmation' => 'nuevaClaveSegura2026',
            'accept_terms' => '0',
        ]);

        $responseWithZero->assertSessionHasErrors('accept_terms');
        $this->assertTrue((bool) $user->fresh()->must_change_password);
        $this->assertNull($user->fresh()->terms_accepted_at);
    }

    /**
     * 4. Al aceptar términos y cambiar contraseña se registra estampa de tiempo e IP probatoria (Ley 527/1999).
     */
    public function test_mandatory_password_change_persists_timestamp_and_ip_evidence(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temporalPassword123'),
            'must_change_password' => true,
            'terms_accepted_at' => null,
            'terms_accepted_ip' => null,
        ]);
        $this->business->users()->attach($user->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $mockIp = '190.84.112.55';

        $response = $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => $mockIp])
            ->post(route('password.change.update'), [
                'current_password' => 'temporalPassword123',
                'password' => 'definitivaSegura2026!',
                'password_confirmation' => 'definitivaSegura2026!',
                'accept_terms' => '1',
            ]);

        $response->assertRedirect(route('dashboard'));

        $freshUser = $user->fresh();
        $this->assertFalse((bool) $freshUser->must_change_password);
        $this->assertNotNull($freshUser->terms_accepted_at);
        $this->assertInstanceOf(Carbon::class, $freshUser->terms_accepted_at);
        $this->assertEquals($mockIp, $freshUser->terms_accepted_ip);
        $this->assertTrue($freshUser->hasAcceptedTerms());
        $this->assertTrue(Hash::check('definitivaSegura2026!', $freshUser->password));
    }

    /**
     * 5. La pantalla de login incluye enlaces discretos a Términos y Condiciones y Privacidad.
     */
    public function test_login_screen_contains_links_to_legal_documents(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee(route('legal.terms'));
        $response->assertSee(route('legal.privacy'));
        $response->assertSee('Términos y Condiciones');
        $response->assertSee('Privacidad y Cookies');
    }

    /**
     * 6. Las vistas de registro y edición de clientes contienen la leyenda legal de Habeas Data (Ley 1581 de 2012).
     */
    public function test_customer_forms_contain_habeas_data_statutory_notice(): void
    {
        $this->actingAs($this->adminUser);

        $legalText = 'El comercio certifica que cuenta con la autorización del titular para la recolección y tratamiento de sus datos de contacto conforme a la Ley 1581 de 2012 de Habeas Data.';

        // Vista de creación
        $createResponse = $this->get(route('customers.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee($legalText);

        // Vista de edición
        $customer = Customer::create([
            'business_id' => $this->business->id,
            'name' => 'Cliente Prueba SAS',
            'identification_number' => '900987654-1',
            'email' => 'prueba@cliente.com',
            'phone' => '3001234567',
            'is_active' => true,
        ]);

        $editResponse = $this->get(route('customers.edit', $customer));
        $editResponse->assertStatus(200);
        $editResponse->assertSee($legalText);
    }
}
