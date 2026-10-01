<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrderTest extends TestCase
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
    protected Product $dish1;
    protected Product $dish2;

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
            'name' => 'Fogón Valluno Domicilios A',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantB = Business::create([
            'name' => 'Fogón Valluno Domicilios B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailBusiness = Business::create([
            'name' => 'Comercio Retail',
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
            'name' => 'Comidas Rápidas',
        ]);

        $this->dish1 = Product::create([
            'business_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => 'Hamburguesa Especial',
            'sku' => 'HAM-01',
            'purchase_price' => 10000.00,
            'sale_price' => 25000.00,
            'stock' => 100,
            'min_stock' => 10,
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'is_active' => true,
        ]);

        $this->dish2 = Product::create([
            'business_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => 'Papas Rústicas',
            'sku' => 'PAP-01',
            'purchase_price' => 3000.00,
            'sale_price' => 8000.00,
            'stock' => 100,
            'min_stock' => 10,
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'is_active' => true,
        ]);
    }

    public function test_can_create_delivery_order_with_client_details_and_shipping_fee(): void
    {
        $this->actingAs($this->employeeA);

        $response = $this->post(route('restaurant.orders.store-delivery'), [
            'order_type' => 'delivery',
            'customer_name' => 'Andrea Morales',
            'delivery_phone' => '3167778899',
            'delivery_address' => 'Calle 9 # 45-12, Apto 501',
            'delivery_notes' => 'Timbre 501, portería principal',
            'delivery_fee' => 5000.00,
            'items' => [
                [
                    'product_id' => $this->dish1->id,
                    'quantity' => 2,
                    'notes' => 'Sin cebolla',
                ],
            ],
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('restaurant.orders.deliveries'));
        $response->assertSessionHas('print_kitchen_ticket_id', $order->id);

        $this->assertEquals('delivery', $order->order_type);
        $this->assertEquals($this->employeeA->id, $order->user_id);
        $this->assertEquals('Andrea Morales', $order->customer_name);
        $this->assertEquals('3167778899', $order->delivery_phone);
        $this->assertEquals('Calle 9 # 45-12, Apto 501', $order->delivery_address);
        $this->assertEquals(5000.00, $order->delivery_fee);
        $this->assertEquals(50000.00, $order->subtotal);
        $this->assertEquals(55000.00, $order->total); // 50000 + 5000
    }

    public function test_negative_delivery_fee_is_strictly_rejected(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->post(route('restaurant.orders.store-delivery'), [
            'order_type' => 'delivery',
            'customer_name' => 'Cliente Test',
            'delivery_phone' => '3001234567',
            'delivery_address' => 'Cra 1 # 2-3',
            'delivery_fee' => -1500.00,
        ]);

        $response->assertSessionHasErrors('delivery_fee');
        $this->assertEquals(0, RestaurantOrder::count());
    }

    public function test_can_create_takeout_order_with_zero_delivery_fee(): void
    {
        $this->actingAs($this->employeeA);

        $response = $this->post(route('restaurant.orders.store-delivery'), [
            'order_type' => 'takeout',
            'customer_name' => 'Luis Fernando',
            'delivery_phone' => '3109990011',
            'delivery_fee' => 8000.00, // Debe forzarse a 0.00 en takeout
            'items' => [
                [
                    'product_id' => $this->dish1->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();
        $this->assertNotNull($order);

        $this->assertEquals('takeout', $order->order_type);
        $this->assertEquals(0.00, $order->delivery_fee);
        $this->assertEquals(25000.00, $order->subtotal);
        $this->assertEquals(25000.00, $order->total);
    }

    public function test_order_total_sums_subtotal_and_delivery_fee_authoritatively(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->post(route('restaurant.orders.store-delivery'), [
            'order_type' => 'delivery',
            'customer_name' => 'Javier Peña',
            'delivery_phone' => '3115554433',
            'delivery_address' => 'Av Pasoancho # 66-10',
            'delivery_fee' => 6500.00,
            'items' => [
                ['product_id' => $this->dish1->id, 'quantity' => 2], // 50.000
                ['product_id' => $this->dish2->id, 'quantity' => 3], // 24.000
            ],
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();
        $this->assertEquals(74000.00, $order->subtotal);
        $this->assertEquals(6500.00, $order->delivery_fee);
        $this->assertEquals(80500.00, $order->total);
    }

    public function test_delivery_status_transitions(): void
    {
        $this->actingAs($this->employeeA);

        $this->post(route('restaurant.orders.store-delivery'), [
            'order_type' => 'delivery',
            'customer_name' => 'Marcela Cruz',
            'delivery_phone' => '3123456789',
            'delivery_address' => 'Cra 5 # 10-20',
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();
        $this->assertEquals('open', $order->status);

        // 1. Despachar (open/kitchen -> dispatched)
        $response = $this->post(route('restaurant.orders.status.update', $order), [
            'status' => 'dispatched',
        ]);
        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('dispatched', $order->status);

        // 2. Marcar Entregado (dispatched -> delivered)
        $response2 = $this->post(route('restaurant.orders.status.update', $order), [
            'status' => 'delivered',
        ]);
        $response2->assertRedirect();
        $order->refresh();
        $this->assertEquals('delivered', $order->status);
    }

    public function test_dispatch_ticket_generation_in_80mm(): void
    {
        $this->actingAs($this->adminA);

        $this->post(route('restaurant.orders.store-delivery'), [
            'order_type' => 'delivery',
            'customer_name' => 'Felipe Restrepo',
            'delivery_phone' => '3189998877',
            'delivery_address' => 'Calle 18 # 122-30 Casa 4',
            'delivery_notes' => 'Conjunto Residencial Las Palmas, frente a la cancha',
            'delivery_fee' => 4500.00,
            'items' => [
                [
                    'product_id' => $this->dish1->id,
                    'quantity' => 1,
                    'notes' => 'Poco picante',
                ],
            ],
        ]);

        $order = RestaurantOrder::where('business_id', $this->restaurantA->id)->first();

        $response = $this->get(route('restaurant.orders.dispatch-ticket', $order));
        $response->assertOk();

        // Verificaciones de contenido en la tirilla de despacho
        $response->assertSee('TIRILLA DE DESPACHO');
        $response->assertSee('Felipe Restrepo');
        $response->assertSee('3189998877');
        $response->assertSee('Calle 18 # 122-30 Casa 4');
        $response->assertSee('Conjunto Residencial Las Palmas');
        $response->assertSee('Hamburguesa Especial');
        $response->assertSee('Poco picante');
        $response->assertSee('Costo de Domicilio:');
        $response->assertSee('4,500.00');
        $response->assertSee('TOTAL A COBRAR EN DESTINO:');
        $response->assertSee('29,500.00'); // 25.000 + 4.500
    }

    public function test_cross_tenant_delivery_isolation(): void
    {
        $orderB = RestaurantOrder::create([
            'business_id' => $this->restaurantB->id,
            'user_id' => $this->adminB->id,
            'order_number' => 'ORD-B001',
            'order_type' => 'delivery',
            'status' => 'open',
            'customer_name' => 'Cliente Secreto B',
            'delivery_phone' => '3000000000',
            'delivery_address' => 'Dirección B',
            'delivery_fee' => 3000.00,
            'subtotal' => 0.00,
            'total' => 3000.00,
        ]);

        $this->actingAs($this->adminA);

        // Intento de ver tirilla de otro tenant responde 404 o 403
        $response = $this->get(route('restaurant.orders.dispatch-ticket', $orderB));
        $this->assertTrue(in_array($response->status(), [404, 403]));

        // Intento de actualizar estado de otro tenant responde 404 o 403
        $response = $this->post(route('restaurant.orders.status.update', $orderB), [
            'status' => 'dispatched',
        ]);
        $this->assertTrue(in_array($response->status(), [404, 403]));

        $this->assertDatabaseHas('restaurant_orders', [
            'id' => $orderB->id,
            'status' => 'open',
        ]);
    }

    public function test_retail_business_receives_403_forbidden_on_deliveries_module(): void
    {
        $this->actingAs($this->retailUser);

        // Tablero de despachos
        $response = $this->get(route('restaurant.orders.deliveries'));
        $response->assertForbidden();

        // Formulario creación delivery
        $response = $this->get(route('restaurant.orders.create-delivery'));
        $response->assertForbidden();

        // Guardar delivery
        $response = $this->post(route('restaurant.orders.store-delivery'), [
            'order_type' => 'delivery',
            'customer_name' => 'Retail No Permitido',
        ]);
        $response->assertForbidden();
    }

    public function test_delivery_order_creation_persists_registered_customer_id_and_details(): void
    {
        $customer = Customer::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Valentina Restrepo',
            'document' => '1144887766',
            'phone' => '3161234567',
            'address' => 'Av. San Joaquín # 12-45',
            'is_active' => true,
        ]);

        $initialCustomerCount = Customer::count();

        $this->actingAs($this->adminA);

        $payload = [
            'order_type' => 'delivery',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'delivery_phone' => $customer->phone,
            'delivery_address' => $customer->address,
            'delivery_notes' => 'Casa de rejas negras',
            'delivery_fee' => 4500,
            'items' => [
                [
                    'product_id' => $this->dish1->id,
                    'quantity' => 2,
                    'notes' => 'Bien asada',
                ],
            ],
        ];

        $response = $this->post(route('restaurant.orders.store-delivery'), $payload);

        $response->assertRedirect(route('restaurant.orders.deliveries'));
        $this->assertEquals($initialCustomerCount, Customer::count(), 'No se deben duplicar registros de clientes.');

        $order = RestaurantOrder::where('customer_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('delivery', $order->order_type);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals('Valentina Restrepo', $order->customer_name);
        $this->assertEquals('3161234567', $order->delivery_phone);
        $this->assertEquals('Av. San Joaquín # 12-45', $order->delivery_address);
        $this->assertEquals(4500.00, (float) $order->delivery_fee);
    }

    public function test_takeout_order_creation_with_registered_customer_id(): void
    {
        $customer = Customer::create([
            'business_id' => $this->restaurantA->id,
            'name' => 'Andrés Gómez',
            'document' => '94556677',
            'phone' => '3187654321',
            'address' => 'Pasoancho # 50-20',
            'is_active' => true,
        ]);

        $this->actingAs($this->employeeA);

        $payload = [
            'order_type' => 'takeout',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'delivery_phone' => $customer->phone,
            'notes' => 'Recoge en 20 minutos',
            'items' => [
                [
                    'product_id' => $this->dish1->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->post(route('restaurant.orders.store-delivery'), $payload);

        $response->assertRedirect(route('restaurant.orders.deliveries'));

        $order = RestaurantOrder::where('customer_id', $customer->id)->where('order_type', 'takeout')->first();
        $this->assertNotNull($order);
        $this->assertEquals('takeout', $order->order_type);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals(0.00, (float) $order->delivery_fee);
    }
}
