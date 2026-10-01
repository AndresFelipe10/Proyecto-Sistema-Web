<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantRbacTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurantBiz;
    protected Business $retailBiz;
    protected User $restaurantAdmin;
    protected User $restaurantEmployee;
    protected User $retailEmployee;
    protected RestaurantTable $table;
    protected Category $category;
    protected Product $dish;

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

        $this->restaurantBiz = Business::create([
            'name' => 'Fogón Valluno RBAC',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailBiz = Business::create([
            'name' => 'Comercio Retail RBAC',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantAdmin = User::factory()->create();
        $this->restaurantBiz->users()->attach($this->restaurantAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->restaurantEmployee = User::factory()->create();
        $this->restaurantBiz->users()->attach($this->restaurantEmployee->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->retailEmployee = User::factory()->create();
        $this->retailBiz->users()->attach($this->retailEmployee->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->restaurantBiz->id,
            'name' => 'Mesa 10',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->category = Category::create([
            'business_id' => $this->restaurantBiz->id,
            'name' => 'Bebidas Tradicionales',
        ]);

        $this->dish = Product::create([
            'business_id' => $this->restaurantBiz->id,
            'category_id' => $this->category->id,
            'name' => 'Champús Valluno',
            'sku' => 'BEB-01',
            'sale_price' => 7000,
            'stock' => 50,
            'product_type' => 'dish',
            'is_active' => true,
        ]);
    }

    public function test_employee_can_access_salon_tables_orders_and_cash_register(): void
    {
        $this->actingAs($this->restaurantEmployee)
            ->withSession(['current_business_id' => $this->restaurantBiz->id]);

        // 1. Ver mapa de mesas
        $response = $this->get(route('restaurant.tables.index'));
        $response->assertOk();

        // 2. Abrir comanda
        $openResponse = $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->table->id,
            'customer_name' => 'Mesero Pedro',
            'guest_count' => 3,
        ]);
        $openResponse->assertRedirect();
        $this->assertDatabaseHas('restaurant_orders', [
            'business_id' => $this->restaurantBiz->id,
            'table_id' => $this->table->id,
            'customer_name' => 'Mesero Pedro',
            'guest_count' => 3,
        ]);

        // 3. Ver Cuadre de Caja
        $cashResponse = $this->get(route('reports.cash-register'));
        $cashResponse->assertOk();
    }

    public function test_employee_receives_403_forbidden_on_creating_editing_or_deleting_tables(): void
    {
        $this->actingAs($this->restaurantEmployee)
            ->withSession(['current_business_id' => $this->restaurantBiz->id]);

        // Crear mesa
        $this->get(route('restaurant.tables.create'))->assertForbidden();
        $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa Hack',
            'capacity' => 4,
        ])->assertForbidden();

        // Editar mesa
        $this->get(route('restaurant.tables.edit', $this->table))->assertForbidden();
        $this->put(route('restaurant.tables.update', $this->table), [
            'name' => 'Mesa Alterada',
            'capacity' => 8,
        ])->assertForbidden();

        // Eliminar mesa
        $this->delete(route('restaurant.tables.destroy', $this->table))->assertForbidden();
    }

    public function test_employee_receives_403_forbidden_on_products_and_menu_write_actions(): void
    {
        $this->actingAs($this->restaurantEmployee)
            ->withSession(['current_business_id' => $this->restaurantBiz->id]);

        // Intentar crear plato
        $this->get(route('products.create'))->assertForbidden();
        $this->post(route('products.store'), [
            'name' => 'Plato Ilegal',
            'sale_price' => 10000,
        ])->assertForbidden();

        // Intentar editar plato
        $this->get(route('products.edit', $this->dish))->assertForbidden();
        $this->put(route('products.update', $this->dish), [
            'name' => 'Plato Modificado',
            'sale_price' => 5000,
        ])->assertForbidden();

        // Intentar eliminar plato
        $this->delete(route('products.destroy', $this->dish))->assertForbidden();
    }

    public function test_employee_receives_403_forbidden_on_categories_and_expenses(): void
    {
        $this->actingAs($this->restaurantEmployee)
            ->withSession(['current_business_id' => $this->restaurantBiz->id]);

        // Categorías
        $this->get(route('categories.create'))->assertForbidden();
        $this->post(route('categories.store'), ['name' => 'Categoría Test'])->assertForbidden();
        $this->get(route('categories.edit', $this->category))->assertForbidden();
        $this->put(route('categories.update', $this->category), ['name' => 'Categoría Alt'])->assertForbidden();
        $this->delete(route('categories.destroy', $this->category))->assertForbidden();

        // Gastos
        $this->get(route('expenses.index'))->assertForbidden();
        $this->get(route('expenses.create'))->assertForbidden();
        $this->post(route('expenses.store'), [])->assertForbidden();
    }

    public function test_sidebar_hides_admin_links_from_employee_in_restaurant(): void
    {
        $response = $this->actingAs($this->restaurantEmployee)
            ->withSession(['current_business_id' => $this->restaurantBiz->id])
            ->get(route('dashboard'));

        $response->assertOk();

        // Enlaces visibles para empleado en Restaurante
        $response->assertSee('Salón y Mesas');
        $response->assertSee('Comandas');
        $response->assertSee('Domicilios y Despacho');
        $response->assertSee('Cuadre de Caja');
        $response->assertSee('Clientes');

        // Enlaces bloqueados y ocultos para empleado
        $response->assertDontSee('Menú / Productos');
        $response->assertDontSee('Categorías');
        $response->assertDontSee('<i class="bi bi-arrow-left-right me-2"></i> Inventario', false);
        $response->assertDontSee('Proveedores');
        $response->assertDontSee('Gastos');
        $response->assertDontSee('Reportes');
        $response->assertDontSee('Mi negocio');
        $response->assertDontSee('Equipo');
    }

    public function test_sidebar_hides_admin_links_from_employee_in_retail(): void
    {
        $response = $this->actingAs($this->retailEmployee)
            ->withSession(['current_business_id' => $this->retailBiz->id])
            ->get(route('dashboard'));

        $response->assertOk();

        // Enlaces visibles para empleado en Retail
        $response->assertSee('<i class="bi bi-cart-check me-2"></i> Ventas', false);
        $response->assertSee('Cuadre de Caja');
        $response->assertSee('Clientes');

        // Enlaces administrativos ocultos
        $response->assertDontSee('<i class="bi bi-boxes me-2"></i> Productos', false);
        $response->assertDontSee('Categorías');
        $response->assertDontSee('<i class="bi bi-arrow-left-right me-2"></i> Inventario', false);
        $response->assertDontSee('Proveedores');
        $response->assertDontSee('Gastos');
        $response->assertDontSee('Reportes');
        $response->assertDontSee('Mi negocio');
        $response->assertDontSee('Equipo');
    }
}
