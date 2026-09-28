<?php

namespace Tests\Feature\Superadmin;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Administrador']
        );

        $this->superadmin = User::factory()->create([
            'is_superadmin' => true,
        ]);
    }

    /**
     * 1. Test de creación de negocio: al crearse se asignan exactamente 30 días calendario en subscription_ends_at.
     */
    public function test_business_creation_assigns_30_calendar_days_subscription(): void
    {
        $knownNow = Carbon::parse('2026-09-28 10:00:00', 'America/Bogota');
        Carbon::setTestNow($knownNow);

        $response = $this->actingAs($this->superadmin)->post('/superadmin/businesses', [
            'name' => 'Café de las Flores',
            'nit' => '901234567-1',
            'phone' => '3123456789',
            'email' => 'flores@test.com',
            'address' => 'Calle 5 # 22-10',
            'admin_name' => 'Flor Admin',
            'admin_email' => 'floradmin@test.com',
        ]);

        $response->assertRedirect('/superadmin/businesses');
        $response->assertSessionHas('status');

        $business = Business::where('name', 'Café de las Flores')->first();
        $this->assertNotNull($business);

        $this->assertNotNull($business->subscription_starts_at);
        $this->assertNotNull($business->subscription_ends_at);

        // Verifica que la fecha de finalización sea exactamente 30 días después
        $expectedEnd = $knownNow->copy()->addDays(30);
        $this->assertSame(
            $expectedEnd->format('Y-m-d H:i:s'),
            $business->subscription_ends_at->timezone('America/Bogota')->format('Y-m-d H:i:s')
        );

        $this->assertSame(30, $business->daysUntilExpiration());
        $this->assertFalse($business->isExpiringSoon());
        $this->assertFalse($business->isCriticalExpiring());

        Carbon::setTestNow(); // Restablecer
    }

    /**
     * 2. Test de renovación: la acción de superadmin extiende 30 días calendario correctamente.
     */
    public function test_superadmin_renewal_action_extends_active_subscription_by_30_days(): void
    {
        $knownNow = Carbon::parse('2026-09-28 10:00:00', 'America/Bogota');
        Carbon::setTestNow($knownNow);

        $currentEnd = $knownNow->copy()->addDays(10); // Le quedaban 10 días

        $business = Business::create([
            'name' => 'Panadería Central',
            'status' => 'active',
            'subscription_starts_at' => $knownNow->copy()->subDays(20),
            'subscription_ends_at' => $currentEnd,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/superadmin/businesses/{$business->id}/renew-subscription");

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $business->refresh();

        // Debe sumar 30 días a los 10 que ya tenía (total 40 días desde knownNow)
        $expectedEnd = $currentEnd->copy()->addDays(30);
        $this->assertSame(
            $expectedEnd->format('Y-m-d H:i:s'),
            $business->subscription_ends_at->timezone('America/Bogota')->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    /**
     * 2b. Test de renovación cuando la suscripción ya estaba vencida: renueva 30 días a partir de hoy.
     */
    public function test_superadmin_renewal_action_extends_expired_subscription_from_today(): void
    {
        $knownNow = Carbon::parse('2026-09-28 10:00:00', 'America/Bogota');
        Carbon::setTestNow($knownNow);

        $pastEnd = $knownNow->copy()->subDays(5); // Venció hace 5 días

        $business = Business::create([
            'name' => 'Ferretería El Tornillo',
            'status' => 'active',
            'subscription_starts_at' => $knownNow->copy()->subDays(35),
            'subscription_ends_at' => $pastEnd,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/superadmin/businesses/{$business->id}/renew-subscription");

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $business->refresh();

        // Debe sumar 30 días a partir de hoy (knownNow)
        $expectedEnd = $knownNow->copy()->addDays(30);
        $this->assertSame(
            $expectedEnd->format('Y-m-d H:i:s'),
            $business->subscription_ends_at->timezone('America/Bogota')->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    /**
     * 3a. Test de visualización de banner: Con 4 días restantes, el banner NO se renderiza.
     */
    public function test_reminder_banner_does_not_render_when_more_than_3_days_remaining(): void
    {
        $knownNow = Carbon::parse('2026-09-28 08:00:00', 'America/Bogota');
        Carbon::setTestNow($knownNow);

        $business = Business::create([
            'name' => 'Boutique Elegance',
            'status' => 'active',
            'subscription_starts_at' => $knownNow->copy()->subDays(26),
            'subscription_ends_at' => $knownNow->copy()->addDays(4), // 4 días restantes
        ]);

        $tenantUser = User::factory()->create();
        $business->users()->attach($tenantUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->assertSame(4, $business->daysUntilExpiration());
        $this->assertFalse($business->isExpiringSoon());
        $this->assertFalse($business->isCriticalExpiring());

        $response = $this->actingAs($tenantUser)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('<div class="subscription-banner', false);
        $response->assertDontSee('Recordatorio: Tu mensualidad vence');
        $response->assertDontSee('¡Atención! Tu mensualidad vence');

        Carbon::setTestNow();
    }

    /**
     * 3b. Test de visualización de banner: Con 3 días restantes, se renderiza con advertencia (amarillo).
     */
    public function test_reminder_banner_renders_warning_when_3_days_remaining(): void
    {
        $knownNow = Carbon::parse('2026-09-28 08:00:00', 'America/Bogota');
        Carbon::setTestNow($knownNow);

        $endsAt = $knownNow->copy()->addDays(3);

        $business = Business::create([
            'name' => 'Droguería San Jorge',
            'status' => 'active',
            'subscription_starts_at' => $knownNow->copy()->subDays(27),
            'subscription_ends_at' => $endsAt,
        ]);

        $tenantUser = User::factory()->create();
        $business->users()->attach($tenantUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->assertSame(3, $business->daysUntilExpiration());
        $this->assertTrue($business->isExpiringSoon());
        $this->assertFalse($business->isCriticalExpiring());

        $response = $this->actingAs($tenantUser)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('subscription-banner subscription-banner-warning', false);
        $response->assertSee('bi-exclamation-triangle-fill', false);
        $response->assertSee('Recordatorio: Tu mensualidad vence en 3 días');
        $response->assertSee($endsAt->format('d M, Y'));
        $response->assertSee('Renovar por WhatsApp');
        $response->assertSee('https://wa.me/' . config('app.support_whatsapp', '573163765939'), false);

        Carbon::setTestNow();
    }

    /**
     * 3c. Test de visualización de banner: Con 1 día restante, se renderiza con clase crítica (rojo).
     */
    public function test_reminder_banner_renders_critical_danger_when_1_day_remaining(): void
    {
        $knownNow = Carbon::parse('2026-09-28 08:00:00', 'America/Bogota');
        Carbon::setTestNow($knownNow);

        $endsAt = $knownNow->copy()->addDays(1);

        $business = Business::create([
            'name' => 'Librería Atenea',
            'status' => 'active',
            'subscription_starts_at' => $knownNow->copy()->subDays(29),
            'subscription_ends_at' => $endsAt,
        ]);

        $tenantUser = User::factory()->create();
        $business->users()->attach($tenantUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->assertSame(1, $business->daysUntilExpiration());
        $this->assertFalse($business->isExpiringSoon());
        $this->assertTrue($business->isCriticalExpiring());

        $response = $this->actingAs($tenantUser)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('subscription-banner subscription-banner-danger', false);
        $response->assertSee('bi-exclamation-octagon-fill', false);
        $response->assertSee('¡Atención! Tu mensualidad vence mañana');
        $response->assertSee($endsAt->format('d M, Y'));
        $response->assertSee('Reportar Pago');
        $response->assertSee('https://wa.me/' . config('app.support_whatsapp', '573163765939'), false);

        Carbon::setTestNow();
    }

    /**
     * 3d. Test de visualización de banner: Cuando vence hoy (0 días restantes), se renderiza con clase crítica (rojo).
     */
    public function test_reminder_banner_renders_critical_danger_when_expires_today(): void
    {
        $knownNow = Carbon::parse('2026-09-28 08:00:00', 'America/Bogota');
        Carbon::setTestNow($knownNow);

        $endsAt = $knownNow->copy()->endOfDay();

        $business = Business::create([
            'name' => 'Supermercado Central',
            'status' => 'active',
            'subscription_starts_at' => $knownNow->copy()->subDays(30),
            'subscription_ends_at' => $endsAt,
        ]);

        $tenantUser = User::factory()->create();
        $business->users()->attach($tenantUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->assertSame(0, $business->daysUntilExpiration());
        $this->assertFalse($business->isExpiringSoon());
        $this->assertTrue($business->isCriticalExpiring());

        $response = $this->actingAs($tenantUser)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('subscription-banner subscription-banner-danger', false);
        $response->assertSee('¡Atención! Tu mensualidad vence hoy');

        Carbon::setTestNow();
    }

    /**
     * 4. Test UI Superadmin: contraste de logo blanco, alertas y columna Vencimiento Mensualidad.
     */
    public function test_superadmin_views_display_white_brand_and_expiration_columns(): void
    {
        $knownNow = Carbon::parse('2026-09-28 10:00:00', 'America/Bogota');
        Carbon::setTestNow($knownNow);

        $business = Business::create([
            'name' => 'Calzado Moderno',
            'nit' => '900987654-3',
            'status' => 'active',
            'subscription_starts_at' => $knownNow,
            'subscription_ends_at' => $knownNow->copy()->addDays(30),
        ]);

        $response = $this->actingAs($this->superadmin)->get('/superadmin/businesses');

        $response->assertStatus(200);
        $response->assertSee('logo-mark-white.svg');
        $response->assertSee('text-white');
        $response->assertSee('Vencimiento Mensualidad');
        $response->assertSee($knownNow->copy()->addDays(30)->format('d M, Y'));
        $response->assertSee('30 días restantes');
        $response->assertSee('Renovar 30 días');

        // Vista de edición
        $editResponse = $this->actingAs($this->superadmin)->get("/superadmin/businesses/{$business->id}/edit");
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Vencimiento Mensualidad');
        $editResponse->assertSee('Renovar 30 días');

        Carbon::setTestNow();
    }
}
