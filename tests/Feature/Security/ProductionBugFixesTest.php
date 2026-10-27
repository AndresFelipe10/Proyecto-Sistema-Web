<?php

namespace Tests\Feature\Security;

use App\AI\Enums\AiIntent;
use App\AI\Services\AiContextService;
use App\AI\Tools\CustomerAiTools;
use App\AI\Tools\InventoryAiTools;
use App\AI\Tools\ProductAiTools;
use App\AI\Tools\RestaurantAiTools;
use App\AI\Tools\SaleAiTools;
use App\AI\Tools\SupplierAiTools;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Expenses\ExpenseService;
use App\Services\Reports\ReportService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionBugFixesTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $business;
    protected User $adminUser;
    protected User $employeeUser;
    protected Category $category;
    protected Product $dishProduct;
    protected Product $standardProduct;
    protected RestaurantTable $tableA;
    protected RestaurantTable $tableB;

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

        $this->business = Business::create([
            'name' => 'Restaurante Gourmet Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create(['name' => 'Chef Patron']);
        $this->business->users()->attach($this->adminUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employeeUser = User::factory()->create(['name' => 'Mesero Principal']);
        $this->business->users()->attach($this->employeeUser->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'business_id' => $this->business->id,
            'name' => 'Especialidades',
            'is_active' => true,
        ]);

        $this->dishProduct = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'name' => 'Sancocho de Pescado',
            'sku' => 'PLT-SANC-01',
            'product_type' => 'dish',
            'sale_price' => 30000.00,
            'cost_price' => 12000.00,
            'stock' => 20,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $this->standardProduct = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'name' => 'Cerveza Club Colombia',
            'sku' => 'BEV-CLUB-01',
            'product_type' => 'standard',
            'sale_price' => 8000.00,
            'cost_price' => 4500.00,
            'stock' => 50,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        $this->tableA = RestaurantTable::create([
            'business_id' => $this->business->id,
            'name' => 'Mesa 101',
            'capacity' => 4,
            'status' => 'occupied',
        ]);

        $this->tableB = RestaurantTable::create([
            'business_id' => $this->business->id,
            'name' => 'Mesa 102',
            'capacity' => 4,
            'status' => 'available',
        ]);
    }

    /**
     * 1. Sincronización de métodos de pago (Nequi y Daviplata normalizados a transfer con referencia).
     */
    public function test_nequi_and_daviplata_are_normalized_to_transfer_in_split_and_bill(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-TEST-001',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 60000.00,
            'total' => 60000.00,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'payment_status' => 'pending',
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'payment_status' => 'pending',
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        // Pagar item1 vía split-payment con nequi
        $responseSplit = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$item1->id],
                'payments' => [
                    [
                        'method' => 'nequi',
                        'amount' => 30000.00,
                        'reference' => '3157894512',
                    ],
                ],
            ]);

        $responseSplit->assertRedirect();
        $responseSplit->assertSessionHas('success');

        $splitPayment = SalePayment::latest('id')->first();
        $this->assertEquals(PaymentMethod::Transfer, $splitPayment->method);
        $this->assertEquals('transfer', $splitPayment->method->value);
        $this->assertEquals('3157894512', $splitPayment->reference);

        // Cobro final de item2 vía bill con daviplata sin referencia
        $responseBill = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.bill', $order), [
                'payment_method' => 'daviplata',
                'amount' => 30000.00,
            ]);

        $responseBill->assertRedirect();
        $billPayment = SalePayment::latest('id')->first();
        $this->assertEquals(PaymentMethod::Transfer, $billPayment->method);
        $this->assertEquals('Daviplata', $billPayment->reference);
    }

    /**
     * 2. Soporte de cantidades fraccionarias en SaleDetail y retiro parcial fraccionario en RemoveOrderItemRequest.
     */
    public function test_fractional_quantities_in_sale_detail_and_remove_item(): void
    {
        $sale = Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $this->adminUser->id,
            'invoice_number' => 'VTA-FRAC-001',
            'sale_date' => now(),
            'subtotal' => 15000.00,
            'total' => 15000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 0.500,
            'unit_price' => 30000.00,
            'subtotal' => 15000.00,
        ]);

        $this->assertEquals(0.5, (float) $detail->fresh()->quantity);

        // Test retiro de cantidad fraccionaria en orden
        $order = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-FRAC-002',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 45000.00,
            'total' => 45000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1.500,
            'unit_price' => 30000.00,
            'subtotal' => 45000.00,
            'payment_status' => 'pending',
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession([
                'current_business_id' => $this->business->id,
                'business_role' => Role::ROLE_ADMIN,
            ])
            ->delete(route('restaurant.orders.items.destroy', [$order, $item]), [
                'quantity_to_remove' => 0.500,
                'reason' => 'Cliente redujo medio plato por porción infantil',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertEquals(1.0, (float) $item->fresh()->quantity);
        $this->assertEquals(30000.00, (float) $item->fresh()->subtotal);
        $this->assertEquals(30000.00, (float) $order->fresh()->total);
    }

    /**
     * 3. Saneamiento de itemsSubtotal en prepareForValidation de SplitOrderPaymentRequest.
     */
    public function test_split_order_payment_request_sanitizes_items_subtotal(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-TEST-003',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 30000.00,
            'total' => 30000.00,
        ]);

        $pendingItem = RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'payment_status' => 'pending',
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        $paidItem = RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'payment_status' => 'paid',
            'status' => 'served',
            'batch_number' => 1,
        ]);

        // Intentar pagar con single payment_method usando el ítem pendiente
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$pendingItem->id],
                'payment_method' => 'cash',
                'cash_received' => 30000.00,
            ]);

        $response->assertRedirect();
        $this->assertEquals('paid', $pendingItem->fresh()->payment_status);
    }

    /**
     * 4. Prevención de TOCTOU en changeTable con bloqueo pesimista.
     */
    public function test_change_table_prevents_toctou_and_rejects_occupied_destination(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-TEST-004',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 30000.00,
            'total' => 30000.00,
        ]);

        // Simular que tableB fue ocupada justo antes de que inicie la transacción
        $this->tableB->update(['status' => 'occupied']);

        $service = app(RestaurantOrderService::class);

        $this->expectException(ValidationException::class);
        $service->changeTable($order, $this->tableB, $this->adminUser);
    }

    /**
     * 5. Expense unique constraint vs SoftDeletes: se puede re-registrar factura tras soft delete.
     */
    public function test_expense_can_be_re_registered_after_soft_delete(): void
    {
        $supplier = Supplier::create([
            'business_id' => $this->business->id,
            'name' => 'Distribuidora del Valle',
            'identification_number' => '900123456-1',
            'is_active' => true,
        ]);

        $expenseService = app(ExpenseService::class);

        $expense1 = $expenseService->createExpense([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FAC-COLLISION-001',
            'issue_date' => '2026-10-01',
            'category' => ExpenseCategory::Merchandise->value,
            'amount' => 150000.00,
            'status' => 'pending',
        ], $this->business->id, $this->adminUser->id);

        $this->assertEquals('FAC-COLLISION-001', $expense1->invoice_number);

        // Soft delete del gasto
        $expenseService->deleteExpense($expense1);
        $this->assertSoftDeleted('expenses', ['id' => $expense1->id]);

        // Re-crear gasto con el mismo proveedor y mismo invoice_number
        $expense2 = $expenseService->createExpense([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FAC-COLLISION-001',
            'issue_date' => '2026-10-02',
            'category' => ExpenseCategory::Merchandise->value,
            'amount' => 180000.00,
            'status' => 'pending',
        ], $this->business->id, $this->adminUser->id);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense2->id,
            'invoice_number' => 'FAC-COLLISION-001',
            'amount' => 180000.00,
            'deleted_at' => null,
        ]);
    }

    /**
     * 6. AI Tools y conteos exactos antes de limit(15).
     */
    public function test_ai_tools_counts_and_followup_resolution(): void
    {
        $restaurantTools = app(RestaurantAiTools::class);
        $supplierTools = app(SupplierAiTools::class);

        // 1. Open orders
        $resultOrders = $restaurantTools->openOrders($this->business->id);
        $this->assertIsInt($resultOrders->meta['open_count']);

        // 2. Deliveries today (excluye cancelados)
        RestaurantOrder::create([
            'business_id' => $this->business->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'DEL-001',
            'order_type' => 'delivery',
            'status' => 'cancelled',
            'subtotal' => 40000.00,
            'total' => 40000.00,
            'created_at' => now(),
        ]);

        $resultDeliveries = $restaurantTools->deliveriesToday($this->business->id);
        $this->assertEquals(0, $resultDeliveries->meta['count']);

        // 3. Tips aggregation
        Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $this->adminUser->id,
            'invoice_number' => 'VTA-TIP-001',
            'sale_date' => now(),
            'subtotal' => 50000.00,
            'service_fee' => 5000.00,
            'total' => 55000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $resultTips = $restaurantTools->tipsCollected($this->business->id);
        $this->assertEquals(5000.00, $resultTips->meta['total_tips']);
        $this->assertEquals(1, $resultTips->meta['orders_count']);

        // 4. Supplier list real count
        Supplier::create([
            'business_id' => $this->business->id,
            'name' => 'Distribuidora del Valle SAS',
            'identification_number' => '900123456-7',
            'is_active' => true,
        ]);
        $resultSuppliers = $supplierTools->list($this->business->id);
        $this->assertGreaterThanOrEqual(1, $resultSuppliers->meta['count']);

        // 5. Context resolution for INVENTORY_SUMMARY
        $contextService = app(AiContextService::class);
        session()->put('ai_context_' . $this->business->id, [
            'last_intent' => AiIntent::INVENTORY_SUMMARY,
            'last_domain' => 'inventory',
            'last_filters' => [],
            'conversation_history' => [],
        ]);

        $resolved = $contextService->resolveFollowUp('cuales son', $this->business->id);
        $this->assertNotNull($resolved);
        $this->assertTrue(in_array($resolved->intent, [AiIntent::LOW_STOCK_PRODUCTS, AiIntent::LIST_PRODUCTS], true));
    }

    /**
     * 7. Supplier NIT accessor y filtro de inactivos en ExpenseController.
     */
    public function test_supplier_nit_accessor_and_active_supplier_filter(): void
    {
        $activeSup = Supplier::create([
            'business_id' => $this->business->id,
            'name' => 'Proveedor Activo',
            'identification_number' => '800555444-3',
            'is_active' => true,
        ]);

        $inactiveSup = Supplier::create([
            'business_id' => $this->business->id,
            'name' => 'Proveedor Cerrado',
            'identification_number' => '800999888-2',
            'is_active' => false,
        ]);

        $this->assertEquals('800555444-3', $activeSup->nit);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('expenses.create'));

        $response->assertOk();
        $response->assertSee('Proveedor Activo');
        $response->assertDontSee('Proveedor Cerrado');
    }

    /**
     * 8. ResetTenantDataCommand con --keep-menu genera saldo inicial para productos con stock.
     */
    public function test_reset_tenant_data_with_keep_menu_preserves_products_and_creates_initial_balance_movement(): void
    {
        $this->artisan("tenant:reset-data {$this->adminUser->email} --keep-menu --force")
            ->assertExitCode(0);

        // Los productos no se eliminaron
        $this->assertDatabaseHas('products', ['id' => $this->dishProduct->id]);
        $this->assertDatabaseHas('products', ['id' => $this->standardProduct->id]);

        // Se generó movimiento de saldo inicial para preservar consistencia del kardex
        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->business->id,
            'product_id' => $this->standardProduct->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 50.000,
            'reason' => 'Saldo inicial tras reseteo de datos de prueba',
        ]);
    }

    /**
     * 9. Eliminación de producto con movimientos de inventario debe desactivar sin error 500.
     */
    public function test_product_with_inventory_movements_is_deactivated_instead_of_throwing_500(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->business->id,
            'name' => 'Producto Con Kardex',
            'sku' => 'KARD-001',
            'cost_price' => 10000,
            'sale_price' => 18000,
            'stock' => 5,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        InventoryMovement::withoutGlobalScopes()->create([
            'business_id' => $this->business->id,
            'product_id' => $product->id,
            'user_id' => $this->adminUser->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 5,
            'previous_stock' => 0,
            'new_stock' => 5,
            'reason' => 'Entrada inicial',
            'movement_date' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('info');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => 0,
        ]);
    }

    /**
     * 10. Cobro dividido soporta cash_received >= 1.000.000 y rechaza ítems pagados o cancelados.
     */
    public function test_split_order_payment_with_millions_cash_received_and_rejects_paid_or_cancelled_items(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-SPLIT-MILLION',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 90000.00,
            'total' => 90000.00,
        ]);

        $pendingItem = RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'payment_status' => 'pending',
            'status' => 'served',
            'batch_number' => 1,
        ]);

        $paidItem = RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'payment_status' => 'paid',
            'status' => 'served',
            'batch_number' => 1,
        ]);

        $cancelledItem = RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'payment_status' => 'pending',
            'status' => 'cancelled',
            'batch_number' => 1,
        ]);

        // 1. Intentar liquidar ítem ya pagado debe fallar con error en 'items'
        $responsePaid = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$paidItem->id],
                'payments' => [
                    ['method' => 'cash', 'amount' => 30000.00],
                ],
            ]);

        $responsePaid->assertSessionHasErrors('items');

        // 2. Intentar liquidar ítem cancelado debe fallar con error en 'items'
        $responseCancelled = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$cancelledItem->id],
                'payments' => [
                    ['method' => 'cash', 'amount' => 30000.00],
                ],
            ]);

        $responseCancelled->assertSessionHasErrors('items');

        // 3. Liquidar ítem pendiente enviando efectivo de más de 1 millón formateado como "1.250.000"
        $responseValid = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$pendingItem->id],
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => '30.000',
                        'cash_received' => '1.250.000',
                    ],
                ],
            ]);

        $responseValid->assertRedirect();
        $this->assertEquals('paid', $pendingItem->fresh()->payment_status);
    }

    /**
     * 11. Conteo real de registros en herramientas de IA sin truncamiento por limit.
     */
    public function test_ai_tools_report_real_total_count_when_records_exceed_limits(): void
    {
        // 1. Clientes: crear 20 clientes activos
        for ($i = 1; $i <= 20; $i++) {
            Customer::withoutGlobalScopes()->create([
                'business_id' => $this->business->id,
                'name' => sprintf('Cliente AI %02d', $i),
                'document' => sprintf('900%05d', $i),
                'is_active' => true,
            ]);
        }

        $customerTools = new CustomerAiTools();
        $customerResult = $customerTools->list($this->business->id);

        $this->assertEquals(20, $customerResult->meta['count']);
        $this->assertCount(15, $customerResult->data);
        $this->assertStringContainsString('20 clientes activos', $customerResult->summary);

        // 2. Productos: crear 20 productos adicionales
        for ($i = 1; $i <= 20; $i++) {
            Product::withoutGlobalScopes()->create([
                'business_id' => $this->business->id,
                'name' => sprintf('Articulo AI %02d', $i),
                'sku' => sprintf('AI-SKU-%02d', $i),
                'cost_price' => 5000,
                'sale_price' => 10000,
                'stock' => 10,
                'min_stock' => 2,
                'is_active' => true,
            ]);
        }

        $productTools = new ProductAiTools();
        $productResult = $productTools->list($this->business->id);

        $this->assertGreaterThanOrEqual(20, $productResult->meta['count']);
        $this->assertCount(15, $productResult->data);
        $this->assertStringContainsString("{$productResult->meta['count']} productos en el catálogo", $productResult->summary);

        // 3. Inventario: productos con bajo stock
        for ($i = 1; $i <= 15; $i++) {
            Product::withoutGlobalScopes()->create([
                'business_id' => $this->business->id,
                'name' => sprintf('Bajo Stock %02d', $i),
                'sku' => sprintf('LOW-%02d', $i),
                'cost_price' => 5000,
                'sale_price' => 10000,
                'stock' => 1,
                'min_stock' => 5,
                'is_active' => true,
            ]);
        }

        $inventoryTools = new InventoryAiTools();
        $lowStockResult = $inventoryTools->lowStock($this->business->id);

        $this->assertGreaterThanOrEqual(15, $lowStockResult->meta['count']);
        $this->assertCount(10, $lowStockResult->data);
        $this->assertStringContainsString("{$lowStockResult->meta['count']} productos", $lowStockResult->summary);
    }

    /**
     * 12. Proveedor con gastos históricos es desactivado en vez de eliminado.
     */
    public function test_supplier_with_expenses_is_deactivated_instead_of_deleted(): void
    {
        $supplier = Supplier::create([
            'business_id' => $this->business->id,
            'name' => 'Distribuidora del Valle',
            'identification_number' => '900111222-1',
            'is_active' => true,
        ]);

        Expense::create([
            'business_id' => $this->business->id,
            'supplier_id' => $supplier->id,
            'amount' => 50000,
            'category' => ExpenseCategory::Merchandise,
            'issue_date' => now(),
            'status' => 'paid',
            'payment_method' => PaymentMethod::Cash,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->delete(route('suppliers.destroy', $supplier));

        $response->assertRedirect(route('suppliers.index'));
        $response->assertSessionHas('info');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'is_active' => 0,
        ]);
    }

    /**
     * 13. Cliente con órdenes de restaurante es desactivado en vez de eliminado.
     */
    public function test_customer_with_restaurant_orders_is_deactivated_instead_of_deleted(): void
    {
        $customer = Customer::create([
            'business_id' => $this->business->id,
            'name' => 'Comensal Frecuente',
            'phone' => '3110009988',
            'is_active' => true,
        ]);

        RestaurantOrder::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-CUST-001',
            'order_type' => 'table',
            'status' => 'closed',
            'subtotal' => 25000.00,
            'total' => 25000.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('info');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => 0,
        ]);
    }

    /**
     * 14. ExpenseService::getSummaryMetrics calcula agregaciones en SQL puro con exactitud.
     */
    public function test_expense_service_get_summary_metrics_aggregation(): void
    {
        $expenseService = app(ExpenseService::class);

        // Gasto pagado
        Expense::create([
            'business_id' => $this->business->id,
            'amount' => 100000.00,
            'category' => ExpenseCategory::Utilities,
            'issue_date' => now()->toDateString(),
            'status' => 'paid',
            'payment_method' => PaymentMethod::Cash,
            'created_by' => $this->adminUser->id,
        ]);

        // Gasto pendiente no vencido
        Expense::create([
            'business_id' => $this->business->id,
            'amount' => 50000.00,
            'category' => ExpenseCategory::Rent,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        // Gasto pendiente vencido
        Expense::create([
            'business_id' => $this->business->id,
            'amount' => 30000.00,
            'category' => ExpenseCategory::Supplies,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        $metrics = $expenseService->getSummaryMetrics($this->business->id);

        $this->assertEquals(180000.00, $metrics['total_amount']);
        $this->assertEquals(100000.00, $metrics['paid_amount']);
        $this->assertEquals(80000.00, $metrics['pending_amount']);
        $this->assertEquals(30000.00, $metrics['overdue_amount']);
        $this->assertEquals(1, $metrics['overdue_count']);
        $this->assertEquals(3, $metrics['count']);
    }

    /**
     * 15. billOrder() normaliza montos formateados COP ("30.000"), rechaza customer_id de otro tenant y guarda snapshot DIAN.
     */
    public function test_bill_order_normalizes_cop_currency_and_rejects_foreign_customer(): void
    {
        $otherBusiness = Business::create([
            'name' => 'Otro Restaurante Tenant B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $foreignCustomer = Customer::create([
            'business_id' => $otherBusiness->id,
            'name' => 'Cliente Forastero',
            'document' => '999888777',
        ]);

        $table = RestaurantTable::create([
            'business_id' => $this->business->id,
            'name' => 'Mesa Facturación COP',
            'capacity' => 4,
            'status' => 'occupied',
        ]);

        $order = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'table_id' => $table->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-TEST-COP-01',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 30000.00,
            'total' => 30000.00,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'product_id' => $this->dishProduct->id,
            'product_name' => $this->dishProduct->name,
            'unit_price' => 30000.00,
            'quantity' => 1,
            'subtotal' => 30000.00,
            'status' => 'served',
            'payment_status' => 'pending',
        ]);

        $orderService = app(RestaurantOrderService::class);

        $sale = $orderService->billOrder($order, [
            'customer_id' => $foreignCustomer->id,
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => '30.000',
                    'cash_received' => '50.000',
                    'reference' => 'Pago efectivo cliente',
                ],
            ],
        ], $this->adminUser);

        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertEquals(30000.00, (float) $sale->total);
        $this->assertEquals(30000.00, (float) $sale->subtotal);

        // El cliente de otro tenant debe haber sido rechazado (null)
        $this->assertNull($sale->customer_id);
        // Debe tener snapshot DIAN por defecto
        $this->assertEquals(config('sales.default_customer_name', 'CONSUMIDOR FINAL'), $sale->customer_name);
        $this->assertEquals(config('sales.default_customer_document', '222222222222'), $sale->customer_document);

        // Medios de pago y desbordamiento VARCHAR(60)
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 30000.00,
            'cash_received' => 50000.00,
            'change_given' => 20000.00,
        ]);

        // La orden y mesa quedan en estado correcto
        $this->assertEquals('billed', $order->fresh()->status);
        $this->assertEquals('available', $table->fresh()->status);
    }

    /**
     * 16. ReportService::getTopProductsReport() preserva cantidades decimales fraccionarias (1.5) y exactitud en COGS y Gross Profit.
     */
    public function test_report_service_top_products_preserves_decimal_quantities_and_cogs(): void
    {
        $sale = Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $this->adminUser->id,
            'sale_date' => now(),
            'invoice_number' => 'INV-FRAC-99',
            'subtotal' => 45000.00,
            'total' => 45000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // DishProduct: sale_price = 30000, cost_price = 12000
        // Cantidad: 1.5 unidades -> Revenue: 45000, COGS: 18000 (1.5 * 12000), Gross Profit: 27000
        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $this->dishProduct->id,
            'quantity' => 1.5,
            'unit_price' => 30000.00,
            'subtotal' => 45000.00,
        ]);

        $reportService = app(ReportService::class);
        $report = $reportService->getTopProductsReport($this->business->id, [
            'date_from' => now()->startOfDay()->toDateString(),
            'date_to' => now()->endOfDay()->toDateString(),
        ]);

        $this->assertEquals(1.5, $report['total_items_sold']);
        $this->assertEquals(45000.00, $report['total_revenue']);
        $this->assertEquals(18000.00, $report['total_cogs']);
        $this->assertEquals(27000.00, $report['total_gross_profit']);

        $item = $report['products']->firstWhere('id', $this->dishProduct->id);
        $this->assertNotNull($item);
        $this->assertEquals(1.5, $item->total_quantity);
        $this->assertEquals(18000.00, $item->cogs);
        $this->assertEquals(27000.00, $item->gross_profit);
    }

    /**
     * 17. RestaurantTableController::destroy() rechaza eliminar mesas en estado 'billed'.
     */
    public function test_restaurant_table_controller_destroy_rejects_billed_table(): void
    {
        $tableBilled = RestaurantTable::create([
            'business_id' => $this->business->id,
            'name' => 'Mesa En Cobro',
            'capacity' => 4,
            'status' => 'billed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->business->id])
            ->delete(route('restaurant.tables.destroy', $tableBilled));

        $response->assertRedirect(route('restaurant.tables.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $tableBilled->id,
            'status' => 'billed',
        ]);
    }

    /**
     * 18. Superadmin\BusinessController::update() conserva subscription_ends_at cuando el campo no se envía en el request.
     */
    public function test_superadmin_business_update_preserves_subscription_ends_at_when_omitted(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $futureDate = now()->addMonths(6)->startOfDay();
        $targetBusiness = Business::create([
            'name' => 'Punto Gourmet con Plan',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
            'subscription_ends_at' => $futureDate,
        ]);

        $response = $this->actingAs($superadmin)
            ->put(route('superadmin.businesses.update', $targetBusiness), [
                'name' => 'Punto Gourmet con Plan Actualizado',
                'business_type' => 'restaurant',
            ]);

        $response->assertRedirect(route('superadmin.businesses.index'));

        $targetBusiness->refresh();
        $this->assertEquals('Punto Gourmet con Plan Actualizado', $targetBusiness->name);
        $this->assertNotNull($targetBusiness->subscription_ends_at);
        $this->assertEquals($futureDate->toDateString(), $targetBusiness->subscription_ends_at->toDateString());
    }

    /**
     * 19. updateDeliveryStatus() rechaza modificar el estado de un pedido que ya está billed o closed, o anular con pagos.
     */
    public function test_update_delivery_status_rejects_modifying_closed_or_billed_orders(): void
    {
        $orderService = app(RestaurantOrderService::class);

        // Caso A: Comanda en estado billed no puede cambiarse
        $orderBilled = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-DEL-BILLED-01',
            'order_type' => 'delivery',
            'status' => 'billed',
            'subtotal' => 25000.00,
            'total' => 25000.00,
        ]);

        try {
            $orderService->updateDeliveryStatus($orderBilled, 'dispatched');
            $this->fail('Se esperaba ValidationException al modificar comanda en estado billed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }

        // Caso B: Comanda en estado closed no puede cambiarse
        $orderClosed = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-DEL-CLOSED-01',
            'order_type' => 'delivery',
            'status' => 'closed',
            'subtotal' => 25000.00,
            'total' => 25000.00,
        ]);

        try {
            $orderService->updateDeliveryStatus($orderClosed, 'delivered');
            $this->fail('Se esperaba ValidationException al modificar comanda en estado closed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }

        // Caso C: Intento de anular comanda que tiene ítems pagados
        $orderPaidItems = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'user_id' => $this->adminUser->id,
            'order_number' => 'ORD-DEL-PAID-01',
            'order_type' => 'delivery',
            'status' => 'open',
            'subtotal' => 30000.00,
            'total' => 30000.00,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $orderPaidItems->id,
            'product_id' => $this->dishProduct->id,
            'product_name' => $this->dishProduct->name,
            'unit_price' => 30000.00,
            'quantity' => 1,
            'subtotal' => 30000.00,
            'status' => 'served',
            'payment_status' => 'paid',
        ]);

        try {
            $orderService->updateDeliveryStatus($orderPaidItems, 'cancelled');
            $this->fail('Se esperaba ValidationException al anular comanda con pagos registrados.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }
    }
}
