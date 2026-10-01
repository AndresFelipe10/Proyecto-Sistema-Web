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

class RestaurantMenuManagementTest extends TestCase
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
            'name' => 'Restaurante Cali Sabor A',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantB = Business::create([
            'name' => 'Restaurante Cali Sabor B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailBusiness = Business::create([
            'name' => 'Almacén Retail',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create(['name' => 'Mesero Admin A']);
        $this->restaurantA->users()->attach($this->adminA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employeeA = User::factory()->create(['name' => 'Mesero Empleado A']);
        $this->restaurantA->users()->attach($this->employeeA->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->adminB = User::factory()->create(['name' => 'Admin B']);
        $this->restaurantB->users()->attach($this->adminB->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->retailUser = User::factory()->create(['name' => 'Retail User']);
        $this->retailBusiness->users()->attach($this->retailUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_restaurant_can_create_dish_with_only_name_and_price_and_auto_generates_sku(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->post(route('products.store'), [
            'name' => 'Bandeja Paisa Criolla',
            'sale_price' => 38000.00,
            'description' => 'Frijoles, arroz, carne molida, chicharrón y huevo',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('status');

        $dish = Product::where('business_id', $this->restaurantA->id)
            ->where('name', 'Bandeja Paisa Criolla')
            ->first();

        $this->assertNotNull($dish);
        $this->assertEquals(38000.00, $dish->sale_price);
        $this->assertStringStartsWith('MNU-', $dish->sku);
        $this->assertEquals(0, $dish->stock);
        $this->assertEquals(0, $dish->min_stock);
        $this->assertTrue((bool) $dish->is_active);
    }

    public function test_restaurant_can_create_dish_with_custom_sku(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->post(route('products.store'), [
            'name' => 'Sancocho de Gallina',
            'sku' => 'SANC-01',
            'sale_price' => 28000.00,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'business_id' => $this->restaurantA->id,
            'name' => 'Sancocho de Gallina',
            'sku' => 'SANC-01',
            'sale_price' => 28000.00,
        ]);
    }

    public function test_restaurant_can_update_dish(): void
    {
        $dish = Product::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Chuleta Valluna',
            'sku' => 'CHUL-01',
            'sale_price' => 26000.00,
            'purchase_price' => 0,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminA);

        $response = $this->put(route('products.update', $dish), [
            'name' => 'Chuleta Valluna Especial',
            'sale_price' => 30000.00,
            'description' => 'Con papas a la francesa y ensalada dulce',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('products.index'));

        $dish->refresh();
        $this->assertEquals('Chuleta Valluna Especial', $dish->name);
        $this->assertEquals(30000.00, $dish->sale_price);
        $this->assertEquals('Con papas a la francesa y ensalada dulce', $dish->description);
    }

    public function test_employee_cannot_create_or_update_dishes(): void
    {
        $this->actingAs($this->employeeA);

        $response = $this->post(route('products.store'), [
            'name' => 'Plato No Permitido',
            'sale_price' => 15000.00,
        ]);
        $response->assertForbidden();

        $dish = Product::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Arroz con Pollo',
            'sku' => 'ARR-01',
            'sale_price' => 22000.00,
            'purchase_price' => 0,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $responseEdit = $this->put(route('products.update', $dish), [
            'name' => 'Intento Modificar',
            'sale_price' => 25000.00,
        ]);
        $responseEdit->assertForbidden();
    }

    public function test_dishes_are_strictly_isolated_between_businesses(): void
    {
        $dishB = Product::create([
            'business_id' => $this->restaurantB->id,
            'name' => 'Plato Secreto B',
            'sku' => 'SEC-01',
            'sale_price' => 50000.00,
            'purchase_price' => 0,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminA);

        $response = $this->get(route('products.edit', $dishB));
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_order_attribution_strictly_assigns_waitperson_and_customer(): void
    {
        $table = RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa 5',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->actingAs($this->employeeA);

        $this->post(route('restaurant.orders.store'), [
            'table_id' => $table->id,
            'customer_name' => 'Juan Carlos Pérez',
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();
        $this->assertNotNull($order);

        // Atendido por = usuario autenticado
        $this->assertEquals($this->employeeA->id, $order->user_id);
        $this->assertEquals('Mesero Empleado A', $order->user->name);

        // Cliente = nombre ingresado
        $this->assertEquals('Juan Carlos Pérez', $order->customer_name);

        // En la vista de show se visualiza claramente el mesero y cliente
        $response = $this->get(route('restaurant.orders.show', $order));
        $response->assertOk();
        $response->assertSee('Mesero / Atendido por:');
        $response->assertSee('Mesero Empleado A');
        $response->assertSee('Juan Carlos Pérez');
    }

    public function test_spanish_status_and_order_type_labels_on_models(): void
    {
        $order = new RestaurantOrder([
            'status' => 'open',
            'order_type' => 'table',
        ]);
        $this->assertEquals('Abierta / En preparación', $order->status_label);
        $this->assertEquals('Mesa / Salón', $order->order_type_label);

        $order->status = 'billed';
        $order->order_type = 'delivery';
        $this->assertEquals('En Cobro / Pre-cuenta emitida', $order->status_label);
        $this->assertEquals('Domicilio', $order->order_type_label);

        $order->status = 'closed';
        $order->order_type = 'takeout';
        $this->assertEquals('Cerrada / Pagada', $order->status_label);
        $this->assertEquals('Para Llevar / Recoger', $order->order_type_label);

        $order->status = 'dispatched';
        $this->assertEquals('En Camino / Despachada', $order->status_label);

        $order->status = 'delivered';
        $this->assertEquals('Entregada', $order->status_label);

        $order->status = 'cancelled';
        $this->assertEquals('Anulada', $order->status_label);

        $table = new RestaurantTable(['status' => 'available']);
        $this->assertEquals('Disponible / Libre', $table->status_label);

        $table->status = 'occupied';
        $this->assertEquals('Ocupada', $table->status_label);

        $table->status = 'billed';
        $this->assertEquals('En Cobro / Pre-cuenta emitida', $table->status_label);
    }

    public function test_order_forms_render_categories_optgroup_and_quantity_buttons(): void
    {
        $category = Category::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Bebidas Frías',
            'is_active' => true,
        ]);

        $dish = Product::create([
            'business_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => 'Limonada de Coco',
            'sku' => 'BEB-001',
            'sale_price' => 8500,
            'cost_price' => 3000,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $table = RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa 1',
            'status' => 'available',
        ]);

        // 1. Probar formulario de apertura de comanda en salón
        $responseCreate = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->get(route('restaurant.orders.create', ['table_id' => $table->id]));

        $responseCreate->assertOk();
        $responseCreate->assertSee('<optgroup label="Bebidas Frías">', false);
        $responseCreate->assertSee('Limonada de Coco');
        $responseCreate->assertSee('btn-qty-minus');
        $responseCreate->assertSee('btn-qty-plus');
        $responseCreate->assertSee('product-search-input');

        // 2. Probar formulario de entrega / domicilio
        $responseDelivery = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->get(route('restaurant.orders.create-delivery'));

        $responseDelivery->assertOk();
        $responseDelivery->assertSee('<optgroup label="Bebidas Frías">', false);
        $responseDelivery->assertSee('Limonada de Coco');
        $responseDelivery->assertSee('btn-qty-minus');
        $responseDelivery->assertSee('btn-qty-plus');
        $responseDelivery->assertSee('product-search-input');

        // 3. Probar vista de detalle de comanda (agregar platos a comanda abierta)
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurantA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $table->id,
            'order_type' => 'table',
            'order_number' => 'CMD-001',
            'status' => 'open',
            'subtotal' => 0,
            'total' => 0,
        ]);

        $responseShow = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->get(route('restaurant.orders.show', $order));

        $responseShow->assertOk();
        $responseShow->assertSee('<optgroup label="Bebidas Frías">', false);
        $responseShow->assertSee('Limonada de Coco');
        $responseShow->assertSee('btn-qty-minus');
        $responseShow->assertSee('btn-qty-plus');
        $responseShow->assertSee('product-search-input');
        $responseShow->assertSee('+ Agregar otro plato');
        $responseShow->assertSee('Confirmar y Enviar a Cocina');
    }

    public function test_restaurant_can_create_dish_with_optional_category_and_tenant_isolation(): void
    {
        $this->actingAs($this->adminA);

        $catA = Category::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Platos Fuertes',
            'is_active' => true,
        ]);

        $catB = Category::create([
            'business_id' => $this->restaurantB->id,
            'name' => 'Categoría de Otro Restaurante',
            'is_active' => true,
        ]);

        // 1. Crear plato con categoría válida
        $responseValid = $this->post(route('products.store'), [
            'name' => 'Ajiaco Santafereño',
            'sale_price' => 32000.00,
            'category_id' => $catA->id,
        ]);
        $responseValid->assertRedirect(route('products.index'));

        $dishValid = Product::where('business_id', $this->restaurantA->id)
            ->where('name', 'Ajiaco Santafereño')
            ->first();
        $this->assertNotNull($dishValid);
        $this->assertEquals($catA->id, $dishValid->category_id);

        // 2. Crear plato sin categoría (vacío / opcional)
        $responseEmpty = $this->post(route('products.store'), [
            'name' => 'Porción de Aguacate',
            'sale_price' => 5000.00,
            'category_id' => '',
        ]);
        $responseEmpty->assertRedirect(route('products.index'));

        $dishEmpty = Product::where('business_id', $this->restaurantA->id)
            ->where('name', 'Porción de Aguacate')
            ->first();
        $this->assertNotNull($dishEmpty);
        $this->assertNull($dishEmpty->category_id);

        // 3. Crear plato con categoría de otro negocio debe fallar (aislamiento tenant)
        $responseCross = $this->post(route('products.store'), [
            'name' => 'Plato Hack',
            'sale_price' => 10000.00,
            'category_id' => $catB->id,
        ]);
        $responseCross->assertSessionHasErrors('category_id');
    }

    public function test_restaurant_orders_show_loads_without_504_for_closed_and_cancelled_orders(): void
    {
        $table = RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa VIP',
            'status' => 'available',
        ]);

        // 1. Orden cerrada (historial)
        $closedOrder = RestaurantOrder::create([
            'business_id' => $this->restaurantA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $table->id,
            'order_type' => 'table',
            'order_number' => 'CMD-CLOSED-01',
            'status' => 'closed',
            'customer_name' => 'Carlos Mesero',
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        $responseClosed = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->get(route('restaurant.orders.show', $closedOrder));

        $responseClosed->assertOk();
        $responseClosed->assertSee('CMD-CLOSED-01');
        $responseClosed->assertSee('Cerrada / Facturada');
        $responseClosed->assertDontSee('+ Agregar otro plato');

        // 2. Orden cancelada
        $cancelledOrder = RestaurantOrder::create([
            'business_id' => $this->restaurantA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $table->id,
            'order_type' => 'table',
            'order_number' => 'CMD-CANC-01',
            'status' => 'cancelled',
            'subtotal' => 0,
            'total' => 0,
        ]);

        $responseCancelled = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->get(route('restaurant.orders.show', $cancelledOrder));

        $responseCancelled->assertOk();
        $responseCancelled->assertSee('CMD-CANC-01');
        $responseCancelled->assertSee('Anulada');
    }

    public function test_waiter_and_customer_semantics_in_table_and_delivery_orders(): void
    {
        $table = RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa 4',
            'status' => 'available',
        ]);

        // 1. En create de mesa se rotula Nombre del Mesero (Opcional)
        $responseCreate = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->get(route('restaurant.orders.create', ['table_id' => $table->id]));

        $responseCreate->assertOk();
        $responseCreate->assertSee('Nombre del Mesero (Opcional)');
        $responseCreate->assertSee('Ej. Juan, Mesa ventana, etc.');

        // 2. En domicilios el nombre del cliente es obligatorio
        $responseDeliveryFail = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->post(route('restaurant.orders.store-delivery'), [
                'order_type' => 'delivery',
                'customer_name' => '',
                'delivery_phone' => '3001234567',
                'delivery_address' => 'Calle 5 # 10-20',
            ]);

        $responseDeliveryFail->assertSessionHasErrors('customer_name');
    }

    public function test_order_forms_render_keyboard_navigation_and_product_search_scripts(): void
    {
        $table = RestaurantTable::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Mesa 5',
            'status' => 'available',
        ]);

        $order = RestaurantOrder::create([
            'business_id' => $this->restaurantA->id,
            'user_id' => $this->adminA->id,
            'table_id' => $table->id,
            'order_type' => 'table',
            'order_number' => 'CMD-NAV-01',
            'status' => 'open',
            'subtotal' => 0,
            'total' => 0,
        ]);

        foreach ([
            route('restaurant.orders.create', ['table_id' => $table->id]),
            route('restaurant.orders.create-delivery'),
            route('restaurant.orders.show', $order),
        ] as $url) {
            $response = $this->actingAs($this->adminA)
                ->withSession(['current_business_id' => $this->restaurantA->id])
                ->get($url);

            $response->assertOk();
            $response->assertSee('product-search-input');
            $response->assertSee('product-result-item');
            $response->assertSee('product-dropdown-list');
            $response->assertSee('input-qty');
            $response->assertSee("e.key === 'Enter'", false);
            $response->assertSee("e.preventDefault()", false);
            $response->assertSee("qtyInput.focus()", false);
            $response->assertSee("qtyInput.select()", false);
        }
    }
}

