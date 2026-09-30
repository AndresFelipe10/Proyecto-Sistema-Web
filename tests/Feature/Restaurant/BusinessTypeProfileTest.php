<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTypeProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $restaurantAdmin;
    protected User $retailAdmin;
    protected Business $restaurantBiz;
    protected Business $retailBiz;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->superadmin = User::factory()->create([
            'is_superadmin' => true,
        ]);

        $this->restaurantBiz = Business::create([
            'name' => 'Restaurante Sabor Valluno',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailBiz = Business::create([
            'name' => 'Ferretería El Tornillo',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantAdmin = User::factory()->create();
        $this->restaurantBiz->users()->attach($this->restaurantAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->retailAdmin = User::factory()->create();
        $this->retailBiz->users()->attach($this->retailAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_create_restaurant_business(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.businesses.store'), [
                'name' => 'Pizzería Napolitana Cali',
                'business_type' => 'restaurant',
                'nit' => '900555666-1',
                'phone' => '3151234567',
                'email' => 'pizzeria@test.com',
                'address' => 'Av 6 Norte # 20-30',
                'admin_name' => 'Mario Rossi',
                'admin_email' => 'mario@test.com',
                'admin_password' => 'Password123!',
            ]);

        $response->assertRedirect(route('superadmin.businesses.index'));
        $this->assertDatabaseHas('businesses', [
            'name' => 'Pizzería Napolitana Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
        ]);
    }

    public function test_superadmin_can_create_retail_business(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.businesses.store'), [
                'name' => 'Calzado La 14',
                'business_type' => 'retail',
                'admin_name' => 'Carlos Vendedor',
                'admin_email' => 'carlos@test.com',
                'admin_password' => 'Password123!',
            ]);

        $response->assertRedirect(route('superadmin.businesses.index'));
        $this->assertDatabaseHas('businesses', [
            'name' => 'Calzado La 14',
            'business_type' => 'retail',
        ]);
    }

    public function test_business_type_defaults_to_retail_when_unspecified(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.businesses.store'), [
                'name' => 'Tienda de Barrio Cali',
                'admin_name' => 'Ana Gomez',
                'admin_email' => 'ana@test.com',
                'admin_password' => 'Password123!',
            ]);

        $response->assertRedirect(route('superadmin.businesses.index'));
        $this->assertDatabaseHas('businesses', [
            'name' => 'Tienda de Barrio Cali',
            'business_type' => 'retail',
        ]);
    }

    public function test_superadmin_can_update_business_type(): void
    {
        $business = Business::create([
            'name' => 'Emprendimiento Mixto',
            'business_type' => 'retail',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->put(route('superadmin.businesses.update', $business), [
                'name' => 'Emprendimiento Mixto Convertido',
                'business_type' => 'restaurant',
            ]);

        $response->assertRedirect(route('superadmin.businesses.index'));
        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'name' => 'Emprendimiento Mixto Convertido',
            'business_type' => 'restaurant',
        ]);
    }

    public function test_business_model_helper_methods_identify_profile_correctly(): void
    {
        $this->assertTrue($this->restaurantBiz->isRestaurant());
        $this->assertFalse($this->restaurantBiz->isRetail());

        $this->assertTrue($this->retailBiz->isRetail());
        $this->assertFalse($this->retailBiz->isRestaurant());
    }

    public function test_sidebar_shows_restaurant_specific_links_for_restaurant_tenant(): void
    {
        $response = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBiz->id])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Platos e Insumos');
        $response->assertSee('Recetas');
    }

    public function test_sidebar_shows_standard_retail_links_for_retail_tenant(): void
    {
        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBiz->id])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Productos');
        $response->assertDontSee('Recetas');
    }
}
