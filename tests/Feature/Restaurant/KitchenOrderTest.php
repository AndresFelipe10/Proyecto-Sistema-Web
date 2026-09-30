<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurantA;
    protected Business $restaurantB;
    protected Business $retailBusiness;
    protected User $adminA;
    protected User $employeeA;
    protected User $adminB;
    protected User $retailUser;
    protected RestaurantTable $tableA;
    protected Product $dishA;
    protected Product $dishB;

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

        $this->restaurantA = Business::create([
            'name' => 'Fogón Valluno A',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantB = Business::create([
            'name' => 'Fogón Valluno B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailBusiness = Business::create([
            'name' => 'Ferretería Retail',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create();
        $this->restaurantA->users()->attach($this->adminA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employeeA = User::factory()->create();
        $this->restaurantA->users()->attach($this->employeeA->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->adminB = User::factory()->create();
        $this->restaurantB->users()->attach($this->adminB->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->retailUser = User::factory()->create();
        $this->retailBusiness->users()->attach($this->retailUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Platos Fuertes',
        ]);

        $this->tableA = RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->dishA = Product::create([
            'business_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => 'Lomo al Trapo',
            'sku' => 'LOMO-01',
            'purchase_price' => 15000.00,
            'sale_price' => 35000.00,
            'stock' => 50,
            'min_stock' => 5,
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'is_active' => true,
        ]);

        $this->dishB = Product::create([
            'business_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => 'Ceviche Mixto',
            'sku' => 'CEV-01',
            'purchase_price' => 12000.00,
            'sale_price' => 28000.00,
            'stock' => 30,
            'min_stock' => 5,
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'is_active' => true,
        ]);
    }

    public function test_opening_order_on_free_table_marks_it_occupied_and_generates_order_number(): void
    {
        $this->actingAs($this->employeeA);

        $response = $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->tableA->id,
            'customer_name' => 'Familia Gómez',
            'notes' => 'Celebración cumpleaños',
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('ORD-0001', $order->order_number);
        $this->assertEquals('open', $order->status);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        // Mesa cambia a occupied
        $this->tableA->refresh();
        $this->assertEquals('occupied', $this->tableA->status);
    }

    public function test_cannot_open_order_on_already_occupied_table(): void
    {
        $this->tableA->update(['status' => 'occupied']);

        $this->actingAs($this->employeeA);

        $response = $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->tableA->id,
            'customer_name' => 'Otro cliente',
        ]);

        $response->assertSessionHasErrors('table_id');
        $this->assertEquals(0, RestaurantOrder::count());
    }

    public function test_add_dishes_with_individual_notes_persists_correctly(): void
    {
        $this->actingAs($this->adminA);

        // Abrir orden
        $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->tableA->id,
            'customer_name' => 'Carlos Restrepo',
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();

        // Agregar platos con notas individuales
        $response = $this->post(route('restaurant.orders.items.store', $order), [
            'items' => [
                [
                    'product_id' => $this->dishA->id,
                    'quantity' => 2,
                    'notes' => 'Término 3/4, sin sal marina',
                ],
                [
                    'product_id' => $this->dishB->id,
                    'quantity' => 1,
                    'notes' => 'Picante bajo, ají aparte',
                ],
            ],
        ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        $this->assertDatabaseHas('restaurant_order_items', [
            'order_id' => $order->id,
            'product_id' => $this->dishA->id,
            'quantity' => 2.000,
            'unit_price' => 35000.00,
            'subtotal' => 70000.00,
            'notes' => 'Término 3/4, sin sal marina',
            'status' => 'pending',
            'printed_to_kitchen' => false,
            'batch_number' => 1,
        ]);

        $this->assertDatabaseHas('restaurant_order_items', [
            'order_id' => $order->id,
            'product_id' => $this->dishB->id,
            'quantity' => 1.000,
            'unit_price' => 28000.00,
            'subtotal' => 28000.00,
            'notes' => 'Picante bajo, ají aparte',
            'status' => 'pending',
            'printed_to_kitchen' => false,
            'batch_number' => 1,
        ]);

        $order->refresh();
        $this->assertEquals(98000.00, $order->total);
    }

    public function test_adding_items_at_different_times_increments_batch_number_after_kitchen_dispatch(): void
    {
        $this->actingAs($this->adminA);

        // 1. Abrir comanda
        $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->tableA->id,
        ]);
        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();

        // 2. Tanda 1 de platos
        $this->post(route('restaurant.orders.items.store', $order), [
            'items' => [
                [
                    'product_id' => $this->dishA->id,
                    'quantity' => 1,
                    'notes' => 'Entrada rápida',
                ],
            ],
        ]);

        $item1 = $order->items()->first();
        $this->assertEquals(1, $item1->batch_number);

        // 3. Enviar Tanda 1 a cocina
        $this->post(route('restaurant.orders.kitchen-ticket', $order));

        $item1->refresh();
        $this->assertTrue((bool) $item1->printed_to_kitchen);
        $this->assertEquals('kitchen', $item1->status);

        // 4. Agregar Tanda 2 posteriormente
        $this->post(route('restaurant.orders.items.store', $order), [
            'items' => [
                [
                    'product_id' => $this->dishB->id,
                    'quantity' => 2,
                    'notes' => 'Platos de fondo',
                ],
            ],
        ]);

        $item2 = $order->items()->where('product_id', $this->dishB->id)->first();
        $this->assertEquals(2, $item2->batch_number);
        $this->assertFalse((bool) $item2->printed_to_kitchen);
        $this->assertEquals('pending', $item2->status);
    }

    public function test_kitchen_ticket_generation_only_includes_unprinted_items_marks_printed_and_omits_prices(): void
    {
        $this->actingAs($this->adminA);

        $this->post(route('restaurant.orders.store'), [
            'table_id' => $this->tableA->id,
        ]);
        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();

        // Tanda 1
        $this->post(route('restaurant.orders.items.store', $order), [
            'items' => [
                ['product_id' => $this->dishA->id, 'quantity' => 2, 'notes' => 'Sin sal'],
            ],
        ]);

        // Primera impresión a cocina
        $response = $this->post(route('restaurant.orders.kitchen-ticket', $order));
        $response->assertOk();
        $response->assertSee('COMANDA DE COCINA');
        $response->assertSee('Mesa 1');
        $response->assertSee('Lomo al Trapo');
        $response->assertSee('Sin sal');

        // REGLA ESTRICTA DE COCINA: Cero precios y cero subtotales
        $response->assertDontSee('$35.000');
        $response->assertDontSee('$70.000');
        $response->assertDontSee('35000');
        $response->assertDontSee('70000');
        $response->assertDontSee('Subtotal');
        $response->assertDontSee('Total:');

        // Verificar que los ítems fueron marcados como impresos
        $itemA = $order->items()->where('product_id', $this->dishA->id)->first();
        $this->assertTrue((bool) $itemA->printed_to_kitchen);
        $this->assertEquals('kitchen', $itemA->status);

        // Añadir Tanda 2
        $this->post(route('restaurant.orders.items.store', $order), [
            'items' => [
                ['product_id' => $this->dishB->id, 'quantity' => 1, 'notes' => 'Ají extra'],
            ],
        ]);

        // Segunda impresión a cocina: SOLO debe incluir los nuevos ítems no impresos
        $response2 = $this->post(route('restaurant.orders.kitchen-ticket', $order));
        $response2->assertOk();
        $response2->assertSee('Ceviche Mixto');
        $response2->assertSee('Ají extra');

        // Lomo al Trapo ya fue impreso en tanda previa, no debe aparecer en este ticket de tanda nueva
        $response2->assertDontSee('Lomo al Trapo');
    }

    public function test_cross_tenant_order_isolation(): void
    {
        // Mesa y orden en restaurantB
        $tableB = RestaurantTable::create([
            'business_id' => $this->restaurantB->id,
            'name' => 'Mesa B1',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $orderB = RestaurantOrder::create([
            'business_id' => $this->restaurantB->id,
            'table_id' => $tableB->id,
            'user_id' => $this->adminB->id,
            'order_number' => 'ORD-0001',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 0.00,
            'total' => 0.00,
        ]);

        // AdminA intenta ver orden de B
        $this->actingAs($this->adminA);

        $response = $this->get(route('restaurant.orders.show', $orderB));
        $this->assertTrue(in_array($response->status(), [404, 403]));

        // AdminA intenta imprimir ticket de B
        $response = $this->post(route('restaurant.orders.kitchen-ticket', $orderB));
        $this->assertTrue(in_array($response->status(), [404, 403]));
    }

    public function test_retail_business_receives_403_forbidden_on_order_routes(): void
    {
        $this->actingAs($this->retailUser);

        $response = $this->get(route('restaurant.orders.index'));
        $response->assertForbidden();

        $response = $this->get(route('restaurant.orders.create'));
        $response->assertForbidden();

        $response = $this->post(route('restaurant.orders.store'), [
            'table_id' => 1,
        ]);
        $response->assertForbidden();
    }
}
