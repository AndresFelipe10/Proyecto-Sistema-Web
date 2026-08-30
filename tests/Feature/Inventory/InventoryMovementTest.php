<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Business;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUser;
    protected User $employeeUser;
    protected Product $productA;

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

        $this->businessA = Business::create(['name' => 'Calzado Cali A']);
        $this->businessB = Business::create(['name' => 'Calzado Cali B']);

        $this->adminUser = User::factory()->create();
        $this->businessA->users()->attach($this->adminUser->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUser = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUser->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->productA = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Zapatillas Deportivas',
            'sku' => 'ZAP-001',
            'cost_price' => 50000,
            'sale_price' => 90000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    public function test_admin_and_employee_can_view_inventory_history(): void
    {
        $adminResponse = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('inventory.index'));

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Historial de Movimientos de Inventario');

        $empResponse = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('inventory.index'));

        $empResponse->assertStatus(200);
        $empResponse->assertSee('Historial de Movimientos de Inventario');
    }

    public function test_entry_movement_increases_stock_and_creates_record(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('inventory.store'), [
                'product_id' => $this->productA->id,
                'type' => 'entry',
                'quantity' => 5,
                'reason' => 'Compra a proveedor',
            ]);

        $response->assertRedirect(route('inventory.index'));

        $this->productA->refresh();
        $this->assertEquals(15, $this->productA->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->businessA->id,
            'product_id' => $this->productA->id,
            'type' => 'entry',
            'quantity' => 5,
            'previous_stock' => 10,
            'new_stock' => 15,
            'reason' => 'Compra a proveedor',
        ]);
    }

    public function test_exit_movement_decreases_stock_and_creates_record(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('inventory.store'), [
                'product_id' => $this->productA->id,
                'type' => 'exit',
                'quantity' => 4,
                'reason' => 'Producto deteriorado',
            ]);

        $response->assertRedirect(route('inventory.index'));

        $this->productA->refresh();
        $this->assertEquals(6, $this->productA->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->businessA->id,
            'product_id' => $this->productA->id,
            'type' => 'exit',
            'quantity' => 4,
            'previous_stock' => 10,
            'new_stock' => 6,
            'reason' => 'Producto deteriorado',
        ]);
    }

    public function test_exit_movement_with_insufficient_stock_is_rejected_and_stock_remains_unchanged(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('inventory.store'), [
                'product_id' => $this->productA->id,
                'type' => 'exit',
                'quantity' => 15, // Mayor a los 10 disponibles
                'reason' => 'Salida excesiva',
            ]);

        $response->assertSessionHasErrors(['quantity']);

        $this->productA->refresh();
        $this->assertEquals(10, $this->productA->stock);

        $this->assertDatabaseMissing('inventory_movements', [
            'product_id' => $this->productA->id,
            'reason' => 'Salida excesiva',
        ]);
    }

    public function test_adjustment_movement_sets_exact_stock_count(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('inventory.store'), [
                'product_id' => $this->productA->id,
                'type' => 'adjustment',
                'quantity' => 25, // Ajuste a 25 unidades
                'reason' => 'Conteo físico anual',
            ]);

        $response->assertRedirect(route('inventory.index'));

        $this->productA->refresh();
        $this->assertEquals(25, $this->productA->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->businessA->id,
            'product_id' => $this->productA->id,
            'type' => 'adjustment',
            'previous_stock' => 10,
            'new_stock' => 25,
            'quantity' => 15, // Diferencia de 15
            'reason' => 'Conteo físico anual',
        ]);
    }

    public function test_movements_respect_tenant_isolation(): void
    {
        $productB = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Producto Empresa B',
            'sku' => 'SKU-B',
            'cost_price' => 20000,
            'sale_price' => 40000,
            'stock' => 5,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        InventoryMovement::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'product_id' => $this->productA->id,
            'user_id' => $this->adminUser->id,
            'type' => 'entry',
            'quantity' => 2,
            'previous_stock' => 8,
            'new_stock' => 10,
            'reason' => 'Movimiento Exclusivo Empresa A',
            'movement_date' => now(),
        ]);

        InventoryMovement::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'product_id' => $productB->id,
            'user_id' => $this->adminUser->id,
            'type' => 'entry',
            'quantity' => 5,
            'previous_stock' => 0,
            'new_stock' => 5,
            'reason' => 'Movimiento Exclusivo Empresa B',
            'movement_date' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('inventory.index'));

        $response->assertSee('Movimiento Exclusivo Empresa A');
        $response->assertDontSee('Movimiento Exclusivo Empresa B');
    }

    /**
     * Criterio de aceptación del ROADMAP:
     * Test de concurrencia cuando stock=1 y dos operaciones simultáneas compiten por el mismo producto.
     */
    public function test_concurrent_stock_reduction_with_pessimistic_locking_prevents_negative_stock(): void
    {
        $criticalProduct = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Última Unidad en Stock',
            'sku' => 'ULT-001',
            'cost_price' => 50000,
            'sale_price' => 90000,
            'stock' => 1, // Exactamente 1 en existencia
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $inventoryService = app(InventoryService::class);

        // Operación 1 (Venta / Salida A)
        $movement1 = $inventoryService->registerMovement(
            product: $criticalProduct->id,
            type: InventoryMovement::TYPE_EXIT,
            quantity: 1,
            reason: 'Venta concurrente 1',
            userId: $this->adminUser->id
        );

        $this->assertEquals(0, $movement1->new_stock);

        // Operación 2 simultánea intentando consumir la misma unidad ya agotada
        $exceptionCaught = false;

        try {
            $inventoryService->registerMovement(
                product: $criticalProduct->id,
                type: InventoryMovement::TYPE_EXIT,
                quantity: 1,
                reason: 'Venta concurrente 2',
                userId: $this->employeeUser->id
            );
        } catch (InsufficientStockException $e) {
            $exceptionCaught = true;
            $this->assertEquals(0, $e->getAvailableStock());
            $this->assertEquals(1, $e->getRequestedQuantity());
        }

        $this->assertTrue($exceptionCaught, 'La segunda operación concurrente debió ser rechazada por stock insuficiente.');

        $criticalProduct->refresh();
        $this->assertEquals(0, $criticalProduct->stock);

        // Validar que solo existe un registro de salida para esta unidad
        $movementCount = InventoryMovement::withoutGlobalScopes()
            ->where('product_id', $criticalProduct->id)
            ->count();

        $this->assertEquals(1, $movementCount);
    }
}
