<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected Business $otherRestaurant;
    protected User $admin;
    protected User $waiter;
    protected User $otherAdmin;
    protected RestaurantTable $table;
    protected Category $category;
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

        $this->restaurant = Business::create([
            'name' => 'Restaurante Sabor Pacífico Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->otherRestaurant = Business::create([
            'name' => 'Restaurante Competencia Palmira',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Admin Restaurante']);
        $this->restaurant->users()->attach($this->admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->waiter = User::factory()->create(['name' => 'Mesero Diego']);
        $this->restaurant->users()->attach($this->waiter->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->otherAdmin = User::factory()->create(['name' => 'Admin Foráneo']);
        $this->otherRestaurant->users()->attach($this->otherAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 4 Terraza',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Platos Fuertes',
        ]);

        $this->dish1 = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Arroz con Mariscos',
            'sku' => 'PLT-ARROZ-01',
            'product_type' => 'dish',
            'sale_price' => 35000.00,
            'stock' => 15,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $this->dish2 = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Ceviche Mixto Especial',
            'sku' => 'PLT-CEVICHE-01',
            'product_type' => 'dish',
            'sale_price' => 28000.00,
            'stock' => 20,
            'min_stock' => 3,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Usuario no-admin recibe 403 Forbidden al intentar eliminar un ítem o anular la comanda.
     */
    public function test_non_admin_user_receives_403_forbidden_when_attempting_to_remove_item_or_cancel_order(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->waiter->id,
            'order_number' => 'ORD-1001',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 35000.00,
            'total' => 35000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 35000.00,
            'subtotal' => 35000.00,
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        // 1. Empleado/mesero intenta eliminar un ítem
        $responseEmployeeItem = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item]), [
                'reason' => 'Error de digitación del mesero',
                'quantity_to_remove' => 1,
            ]);
        $responseEmployeeItem->assertForbidden();

        // 2. Empleado/mesero intenta anular la comanda completa
        $responseEmployeeCancel = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.cancel', $order), [
                'reason' => 'Cliente se retiró del establecimiento',
            ]);
        $responseEmployeeCancel->assertForbidden();

        // 3. Admin de otro tenant intenta atacar la comanda (Aislamiento Multi-Tenant CWE-284 / Global Scope)
        $responseCrossTenantItem = $this->actingAs($this->otherAdmin)
            ->withSession(['current_business_id' => $this->otherRestaurant->id])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item]), [
                'reason' => 'Ataque malicioso cross-tenant',
                'quantity_to_remove' => 1,
            ]);
        $responseCrossTenantItem->assertNotFound();

        $responseCrossTenantCancel = $this->actingAs($this->otherAdmin)
            ->withSession(['current_business_id' => $this->otherRestaurant->id])
            ->post(route('restaurant.orders.cancel', $order), [
                'reason' => 'Ataque malicioso cross-tenant',
            ]);
        $responseCrossTenantCancel->assertNotFound();

        // Verificar que los datos permanecen intactos
        $this->assertEquals('open', $order->fresh()->status);
        $this->assertEquals('pending', $item->fresh()->status);
    }

    /**
     * Test 2: Admin elimina un ítem exitosamente: se restaura el stock del ítem, se recalcula el total de la comanda y se audita el motivo.
     */
    public function test_admin_removes_item_successfully_restoring_stock_recalculating_totals_and_auditing_reason(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-1002',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 91000.00,
            'total' => 91000.00, // 1 Arroz ($35.000) + 2 Ceviches ($56.000) = $91.000
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 35000.00,
            'subtotal' => 35000.00,
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish2->id,
            'quantity' => 2,
            'unit_price' => 28000.00,
            'subtotal' => 56000.00,
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        $initialStockDish1 = (float) $this->dish1->stock; // 15
        $reason = 'Plato devuelto porque el comensal cambió de opinión por ceviche';

        // Admin elimina el ítem 1 (Arroz con Mariscos)
        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item1]), [
                'reason' => $reason,
                'quantity_to_remove' => 1,
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success');

        // 1. Stock restaurado
        $this->dish1->refresh();
        $this->assertEquals($initialStockDish1 + 1, (float) $this->dish1->stock);

        // 2. Movimiento de inventario registrado para auditoría
        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->restaurant->id,
            'product_id' => $this->dish1->id,
            'user_id' => $this->admin->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 1.000,
            'previous_stock' => $initialStockDish1,
            'new_stock' => $initialStockDish1 + 1,
        ]);

        // 3. Auditoría en el ítem de comanda
        $item1->refresh();
        $this->assertEquals('cancelled', $item1->status);
        $this->assertEquals($this->admin->id, $item1->cancelled_by);
        $this->assertNotNull($item1->cancelled_at);
        $this->assertEquals($reason, $item1->cancellation_reason);

        // 4. Totales de la comanda recalculados (ahora solo queda item2: $56.000)
        $order->refresh();
        $this->assertEquals(56000.00, (float) $order->subtotal);
        $this->assertEquals(56000.00, (float) $order->total);

        // 5. Eliminar también el segundo ítem para verificar que la comanda queda en $0
        $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item2]), [
                'reason' => 'Mesa canceló el resto del pedido',
                'quantity_to_remove' => 2,
            ]);

        $order->refresh();
        $this->assertEquals(0.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->total);
    }

    /**
     * Test 3: Admin anula la comanda completa: estado cambia a anulada, se libera la mesa, se restaura el stock de todos los platos y se guardan los datos del admin y motivo.
     */
    public function test_admin_cancels_entire_order_liberating_table_restoring_stock_and_saving_audit_log(): void
    {
        $this->table->update(['status' => 'occupied']);

        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-1003',
            'order_type' => 'table',
            'status' => 'in_kitchen',
            'subtotal' => 91000.00,
            'total' => 91000.00,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 35000.00,
            'subtotal' => 35000.00,
            'status' => 'kitchen',
            'batch_number' => 1,
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish2->id,
            'quantity' => 2,
            'unit_price' => 28000.00,
            'subtotal' => 56000.00,
            'status' => 'kitchen',
            'batch_number' => 1,
        ]);

        $initialStock1 = (float) $this->dish1->stock; // 15
        $initialStock2 = (float) $this->dish2->stock; // 20
        $cancelReason = 'Mesa canceló su consumo y se retiró del restaurante por emergencia familiar';

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.cancel', $order), [
                'reason' => $cancelReason,
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success');

        // 1. Estado de la comanda cambiado a anulada con auditoría
        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals($this->admin->id, $order->cancelled_by);
        $this->assertNotNull($order->cancelled_at);
        $this->assertEquals($cancelReason, $order->cancellation_reason);

        // 2. Mesa liberada (pasa de occupied a available)
        $this->table->refresh();
        $this->assertEquals('available', $this->table->status);

        // 3. Stock restaurado para todos los ítems activos
        $this->dish1->refresh();
        $this->dish2->refresh();
        $this->assertEquals($initialStock1 + 1, (float) $this->dish1->stock);
        $this->assertEquals($initialStock2 + 2, (float) $this->dish2->stock);

        // 4. Ítems marcados como cancelados con auditoría
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals('cancelled', $item1->status);
        $this->assertEquals($this->admin->id, $item1->cancelled_by);
        $this->assertEquals($cancelReason, $item1->cancellation_reason);
        $this->assertEquals('cancelled', $item2->status);
        $this->assertEquals($this->admin->id, $item2->cancelled_by);
        $this->assertEquals($cancelReason, $item2->cancellation_reason);

        // 5. Movimientos de inventario registrados
        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->restaurant->id,
            'product_id' => $this->dish1->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 1.000,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->restaurant->id,
            'product_id' => $this->dish2->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 2.000,
        ]);
    }

    /**
     * Test 4: Se rechaza el intento de anular o eliminar ítems de una comanda que ya fue pagada/facturada.
     */
    public function test_rejects_attempt_to_cancel_or_delete_items_from_already_settled_or_closed_order(): void
    {
        $sale = Sale::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'invoice_number' => 'VTA-000001-0001',
            'sale_date' => now(),
            'subtotal' => 35000.00,
            'tax' => 0.00,
            'total' => 35000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $closedOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'sale_id' => $sale->id,
            'order_number' => 'ORD-1004',
            'order_type' => 'table',
            'status' => 'closed',
            'subtotal' => 35000.00,
            'total' => 35000.00,
            'closed_at' => now(),
        ]);

        $item = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $closedOrder->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 35000.00,
            'subtotal' => 35000.00,
            'status' => 'served',
            'batch_number' => 1,
        ]);

        // 1. Intento de eliminar ítem de comanda cerrada
        $responseItem = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->delete(route('restaurant.orders.items.destroy', [$closedOrder, $item]), [
                'reason' => 'Intento de eliminar ítem de venta ya cerrada',
                'quantity_to_remove' => 1,
            ]);

        $responseItem->assertSessionHasErrors(['order']);
        $this->assertEquals('served', $item->fresh()->status);

        // 2. Intento de anular comanda cerrada
        $responseCancel = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.cancel', $closedOrder), [
                'reason' => 'Intento de anular orden ya facturada',
            ]);

        $responseCancel->assertSessionHasErrors(['order']);
        $this->assertEquals('closed', $closedOrder->fresh()->status);

        // 3. Validación de motivo: debe exigir al menos 4 caracteres
        $openOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-1005',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 0.00,
            'total' => 0.00,
        ]);

        $responseShortReason = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.cancel', $openOrder), [
                'reason' => 'No', // menos de 4 caracteres
            ]);

        $responseShortReason->assertSessionHasErrors(['reason']);
        $this->assertEquals('open', $openOrder->fresh()->status);
    }

    /**
     * Test 5: Reducción parcial de cantidad en ítem de comanda:
     * Comanda con 1 ítem de cantidad 4 y subtotal $100.000 ($25.000 c/u).
     * Admin retira 1 unidad -> Quedan 3 unidades y subtotal $75.000.
     * Se restaura 1 unidad de stock y el total de la comanda pasa a $75.000.
     */
    public function test_admin_partially_reduces_item_quantity_restoring_exact_stock_and_updating_totals(): void
    {
        $dish = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Pollo Asado Especial',
            'sku' => 'PLT-POLLO-01',
            'product_type' => 'dish',
            'sale_price' => 25000.00,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-1006',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 100000.00,
            'total' => 100000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $dish->id,
            'quantity' => 4,
            'unit_price' => 25000.00,
            'subtotal' => 100000.00,
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        $initialStock = (float) $dish->stock; // 10

        // Admin envía petición para retirar 1 unidad con motivo
        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item]), [
                'quantity_to_remove' => 1,
                'reason' => 'Cliente canceló una unidad',
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success');

        // Assert 1: El ítem permanece activo con cantidad 3 y subtotal $75.000
        $item->refresh();
        $this->assertEquals('pending', $item->status);
        $this->assertEquals(3, $item->quantity);
        $this->assertEquals(75000.00, (float) $item->subtotal);
        $this->assertStringContainsString('Reducción de 1 un.', $item->notes);

        // Assert 2: Se registra un movimiento de entrada al stock por exactamente 1 unidad
        $dish->refresh();
        $this->assertEquals($initialStock + 1, (float) $dish->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->restaurant->id,
            'product_id' => $dish->id,
            'user_id' => $this->admin->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 1.000,
            'previous_stock' => $initialStock,
            'new_stock' => $initialStock + 1,
        ]);

        // Assert 3: El total de la comanda se actualiza en $75.000
        $order->refresh();
        $this->assertEquals(75000.00, (float) $order->subtotal);
        $this->assertEquals(75000.00, (float) $order->total);
    }

    /**
     * Test 6: Eliminación total por cantidad completa cuando quantity_to_remove es igual a la cantidad restante.
     */
    public function test_admin_fully_cancels_item_when_quantity_to_remove_equals_current_quantity(): void
    {
        $dish = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Bandeja Paisa',
            'sku' => 'PLT-PAISA-01',
            'product_type' => 'dish',
            'sale_price' => 30000.00,
            'stock' => 8,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-1007',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 90000.00,
            'total' => 90000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $dish->id,
            'quantity' => 3,
            'unit_price' => 30000.00,
            'subtotal' => 90000.00,
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        $initialStock = (float) $dish->stock;

        // Admin retira las 3 unidades completas restantes
        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item]), [
                'quantity_to_remove' => 3,
                'reason' => 'Se cancela la totalidad del plato por error de mesa',
            ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success');

        // Assert: El ítem pasa a 'cancelled' y se audita
        $item->refresh();
        $this->assertEquals('cancelled', $item->status);
        $this->assertEquals($this->admin->id, $item->cancelled_by);
        $this->assertEquals('Se cancela la totalidad del plato por error de mesa', $item->cancellation_reason);

        // Assert: Stock se restaura en las 3 unidades completas
        $dish->refresh();
        $this->assertEquals($initialStock + 3, (float) $dish->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->restaurant->id,
            'product_id' => $dish->id,
            'user_id' => $this->admin->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 3.000,
            'previous_stock' => $initialStock,
            'new_stock' => $initialStock + 3,
        ]);

        // Assert: Comanda pasa a $0
        $order->refresh();
        $this->assertEquals(0.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->total);
    }

    /**
     * Test 7: Rechazar peticiones con quantity_to_remove <= 0 o mayor a la cantidad actual del ítem (422 Unprocessable Entity).
     */
    public function test_validation_rejects_zero_negative_or_exceeding_quantity_to_remove(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-1008',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 70000.00,
            'total' => 70000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 2,
            'unit_price' => 35000.00,
            'subtotal' => 70000.00,
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        // 1. Rechazar quantity_to_remove = 0 (vía JSON -> 422 Unprocessable Entity)
        $responseZeroJson = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->deleteJson(route('restaurant.orders.items.destroy', [$order, $item]), [
                'quantity_to_remove' => 0,
                'reason' => 'Motivo de prueba con 0 unidades',
            ]);
        $responseZeroJson->assertUnprocessable();
        $responseZeroJson->assertJsonValidationErrors(['quantity_to_remove']);

        // 2. Rechazar quantity_to_remove < 0 (vía Web -> Session errors)
        $responseNegative = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item]), [
                'quantity_to_remove' => -2,
                'reason' => 'Motivo de prueba con cantidad negativa',
            ]);
        $responseNegative->assertSessionHasErrors(['quantity_to_remove']);

        // 3. Rechazar quantity_to_remove mayor a la cantidad actual (5 cuando hay 2 -> 422 Unprocessable Entity)
        $responseExceedingJson = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->deleteJson(route('restaurant.orders.items.destroy', [$order, $item]), [
                'quantity_to_remove' => 5,
                'reason' => 'Motivo de prueba intentando retirar más de lo existente',
            ]);
        $responseExceedingJson->assertUnprocessable();
        $responseExceedingJson->assertJsonValidationErrors(['quantity_to_remove']);

        // Verificar que el ítem permanece intacto con cantidad 2 y pending
        $item->refresh();
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals('pending', $item->status);
    }
}
