<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTableChangeTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected Business $otherRestaurant;
    protected User $admin;
    protected User $waiter;
    protected User $otherAdmin;
    protected RestaurantTable $originTable;
    protected RestaurantTable $destinationTable;
    protected RestaurantTable $occupiedTable;
    protected RestaurantTable $foreignTable;
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

        $this->restaurant = Business::create([
            'name' => 'Restaurante Asados La 14 Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->otherRestaurant = Business::create([
            'name' => 'Restaurante Pizzería El Peñón',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Carlos Administrador']);
        $this->restaurant->users()->attach($this->admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->waiter = User::factory()->create(['name' => 'Andrés Mesero']);
        $this->restaurant->users()->attach($this->waiter->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->otherAdmin = User::factory()->create(['name' => 'Admin Competencia']);
        $this->otherRestaurant->users()->attach($this->otherAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Mesas del restaurante activo
        $this->originTable = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 1 Terraza',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $this->destinationTable = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 5 Salón VIP',
            'capacity' => 6,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->occupiedTable = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 2 Salón Central',
            'capacity' => 2,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        // Mesa del otro restaurante (para pruebas multi-tenant)
        $this->foreignTable = RestaurantTable::create([
            'business_id' => $this->otherRestaurant->id,
            'name' => 'Mesa Externa 99',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Carnes y Parrilla',
        ]);

        $this->dish = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Punta de Anca 350g',
            'sku' => 'PLT-CARNE-01',
            'product_type' => 'dish',
            'sale_price' => 42000.00,
            'stock' => 20,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create an active table order.
     */
    protected function createActiveOrder(array $attributes = []): RestaurantOrder
    {
        return RestaurantOrder::create(array_merge([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->originTable->id,
            'user_id' => $this->waiter->id,
            'order_number' => 'ORD-0001',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 3,
            'subtotal' => 42000.00,
            'total' => 42000.00,
        ], $attributes));
    }

    /**
     * Test 1: Usuario con rol mesero/empleado cambia exitosamente de mesa:
     * - Mesa origen pasa a available.
     * - Mesa destino pasa a occupied.
     * - La orden queda vinculada a la nueva mesa.
     */
    public function test_waiter_employee_can_successfully_change_order_table(): void
    {
        $order = $this->createActiveOrder();

        $response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.change-table', $order), [
                'table_id' => $this->destinationTable->id,
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success', "Mesa cambiada exitosamente a: {$this->destinationTable->name}");

        // Mesa origen liberada
        $this->originTable->refresh();
        $this->assertSame('available', $this->originTable->status);

        // Mesa destino ocupada
        $this->destinationTable->refresh();
        $this->assertSame('occupied', $this->destinationTable->status);

        // Orden vinculada a nueva mesa y con nota de auditoría
        $order->refresh();
        $this->assertSame($this->destinationTable->id, $order->table_id);
        $this->assertStringContainsString('[AUDITORÍA]', (string) $order->notes);
        $this->assertStringContainsString('Mesa 1 Terraza', (string) $order->notes);
        $this->assertStringContainsString('Mesa 5 Salón VIP', (string) $order->notes);
        $this->assertStringContainsString($this->waiter->name, (string) $order->notes);
    }

    /**
     * Test 1b: Administrador también puede cambiar exitosamente de mesa.
     */
    public function test_admin_can_also_successfully_change_order_table(): void
    {
        $order = $this->createActiveOrder();

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.change-table', $order), [
                'table_id' => $this->destinationTable->id,
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success', "Mesa cambiada exitosamente a: {$this->destinationTable->name}");

        $order->refresh();
        $this->assertSame($this->destinationTable->id, $order->table_id);
    }

    /**
     * Test 2: Rechazo al intentar cambiar a una mesa que ya está ocupada (status: occupied).
     */
    public function test_rejection_when_attempting_to_change_to_an_occupied_table(): void
    {
        $order = $this->createActiveOrder();

        $response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->from(route('restaurant.orders.show', $order))
            ->post(route('restaurant.orders.change-table', $order), [
                'table_id' => $this->occupiedTable->id,
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHasErrors(['table_id']);

        // Las mesas y la orden conservan sus estados inalterados
        $this->originTable->refresh();
        $this->assertSame('occupied', $this->originTable->status);

        $this->occupiedTable->refresh();
        $this->assertSame('occupied', $this->occupiedTable->status);

        $order->refresh();
        $this->assertSame($this->originTable->id, $order->table_id);
    }

    /**
     * Test 3: Protección multi-tenant: Rechazo 404 o error de validación si se intenta asignar una mesa perteneciente a otro negocio/tenant.
     */
    public function test_multi_tenant_protection_rejects_table_belonging_to_another_business(): void
    {
        $order = $this->createActiveOrder();

        $response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->from(route('restaurant.orders.show', $order))
            ->post(route('restaurant.orders.change-table', $order), [
                'table_id' => $this->foreignTable->id,
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHasErrors(['table_id']);

        // Verificar que la orden no cambió
        $order->refresh();
        $this->assertSame($this->originTable->id, $order->table_id);

        // La mesa ajena no fue modificada
        $this->foreignTable->refresh();
        $this->assertSame('available', $this->foreignTable->status);
    }

    /**
     * Test 3b: Protección multi-tenant: Rechazo 403 si un usuario de otro tenant intenta modificar la comanda.
     */
    public function test_cross_tenant_user_cannot_access_foreign_order(): void
    {
        $order = $this->createActiveOrder();

        $response = $this->actingAs($this->otherAdmin)
            ->withSession(['current_business_id' => $this->otherRestaurant->id])
            ->post(route('restaurant.orders.change-table', $order), [
                'table_id' => $this->destinationTable->id,
            ]);

        // Gracias al Global Scope BelongsToTenant, la comanda ni siquiera existe en el scope del otro tenant (404)
        // o si pasara el scope, es bloqueada por la Policy/Middleware con 403.
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    /**
     * Test 4: Rechazo si la comanda ya está facturada, pagada o cancelada.
     */
    public function test_rejection_if_order_is_billed_closed_or_cancelled(): void
    {
        // 4a. Comanda en estado billed (pre-cuenta)
        $billedOrder = $this->createActiveOrder(['status' => 'billed']);

        $responseBilled = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.change-table', $billedOrder), [
                'table_id' => $this->destinationTable->id,
            ]);

        $responseBilled->assertSessionHasErrors(['order']);
        $billedOrder->refresh();
        $this->assertSame($this->originTable->id, $billedOrder->table_id);

        // 4b. Comanda cerrada / pagada (con venta asociada)
        $sale = Sale::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'invoice_number' => 'FAC-0001',
            'sale_date' => now(),
            'subtotal' => 42000.00,
            'tax' => 0.00,
            'total' => 42000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $closedOrder = $this->createActiveOrder([
            'order_number' => 'ORD-0002',
            'status' => 'closed',
            'sale_id' => $sale->id,
            'closed_at' => now(),
        ]);

        $responseClosed = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.change-table', $closedOrder), [
                'table_id' => $this->destinationTable->id,
            ]);

        $responseClosed->assertSessionHasErrors(['order']);
        $closedOrder->refresh();
        $this->assertSame($this->originTable->id, $closedOrder->table_id);

        // 4c. Comanda anulada / cancelada
        $cancelledOrder = $this->createActiveOrder([
            'order_number' => 'ORD-0003',
            'status' => 'cancelled',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Comensal canceló pedido',
        ]);

        $responseCancelled = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.change-table', $cancelledOrder), [
                'table_id' => $this->destinationTable->id,
            ]);

        $responseCancelled->assertSessionHasErrors(['order']);
        $cancelledOrder->refresh();
        $this->assertSame($this->originTable->id, $cancelledOrder->table_id);
    }

    /**
     * Test 5: Rechazo al intentar seleccionar la misma mesa que la orden ya tiene asignada.
     */
    public function test_rejection_when_selecting_the_same_table(): void
    {
        $order = $this->createActiveOrder();

        $response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->from(route('restaurant.orders.show', $order))
            ->post(route('restaurant.orders.change-table', $order), [
                'table_id' => $this->originTable->id,
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHasErrors(['table_id']);

        $order->refresh();
        $this->assertSame($this->originTable->id, $order->table_id);
    }

    /**
     * Test 6: La interfaz Blade renderiza el botón de cambio de mesa y modal en comandas modificables.
     */
    public function test_show_view_renders_change_table_button_and_modal(): void
    {
        $order = $this->createActiveOrder();

        $response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Cambiar Mesa');
        $response->assertSee('id="changeTableModal"', false);
        $response->assertSee('Mesa 5 Salón VIP');
    }
}
