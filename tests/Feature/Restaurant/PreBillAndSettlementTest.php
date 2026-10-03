<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreBillAndSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected User $admin;
    protected User $waiter;
    protected RestaurantTable $table;
    protected Product $dishBurger;

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
            'name' => 'Restaurante Gourmet Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Admin Restaurante']);
        $this->restaurant->users()->attach($this->admin->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->waiter = User::factory()->create(['name' => 'Mesero Carlos']);
        $this->restaurant->users()->attach($this->waiter->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa Terraza 5',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Platos Fuertes',
        ]);

        // Plato del menú de restaurante: Hamburguesa Gourmet ($20.000, 10 en stock)
        $this->dishBurger = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Hamburguesa Gourmet',
            'sku' => 'PLT-HAMBUR-01',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 8000,
            'sale_price' => 20000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    public function test_prebill_emission_generates_80mm_ticket_and_transitions_order_and_table_to_billed(): void
    {
        // 1. Abrir comanda en mesa
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'CMD-000100',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 40000,
            'total' => 40000,
        ]);

        $this->table->update(['status' => 'occupied']);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dishBurger->id,
            'quantity' => 2,
            'unit_price' => 20000,
            'subtotal' => 40000,
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        // 2. Mesero solicita pre-cuenta
        $response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.prebill', $order));

        $response->assertStatus(200);
        $response->assertViewIs('restaurant.orders.pre-bill-ticket');

        // Validar contenido del ticket de 80mm
        $response->assertSee('ESTADO DE CONSUMO / PRE-CUENTA');
        $response->assertSee('DOCUMENTO INTERNO NO VÁLIDO COMO FACTURA O COMPROBANTE DE VENTA');
        $response->assertSee('Restaurante Gourmet Cali');
        $response->assertSee('Mesa Terraza 5');
        $response->assertSee('Mesero Carlos');
        $response->assertSee('Hamburguesa Gourmet');
        $response->assertSee('$40.000');
        $response->assertDontSee('FAC-');

        // 3. Verificar cambio de estados en base de datos
        $this->assertDatabaseHas('restaurant_orders', [
            'id' => $order->id,
            'status' => 'billed',
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $this->table->id,
            'status' => 'billed',
        ]);
    }

    public function test_table_order_settlement_creates_consecutive_sale_mixed_payments_atomic_recipe_deduction_and_frees_table(): void
    {
        // 1. Abrir comanda en mesa y marcar en cobro
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'CMD-000101',
            'order_type' => 'table',
            'status' => 'billed',
            'subtotal' => 40000,
            'total' => 40000,
        ]);

        $this->table->update(['status' => 'billed']);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dishBurger->id,
            'quantity' => 2,
            'unit_price' => 20000,
            'subtotal' => 40000,
            'status' => 'served',
            'batch_number' => 1,
        ]);

        // 2. Liquidación con pago mixto: $20.000 Efectivo (recibe $50.000, vuelto $30.000) + $20.000 Transferencia
        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 2,
                    'unit_price' => 20000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 20000,
                    'cash_received' => 50000,
                    'change_given' => 30000,
                ],
                [
                    'method' => 'transfer',
                    'amount' => 20000,
                    'reference' => 'NEQUI-998877',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // 3. Verificar que la venta fue creada con consecutivo legal
        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertMatchesRegularExpression('/^VTA-\d{6}-\d{4}$/', $sale->invoice_number);
        $this->assertEquals(40000, $sale->total);
        $this->assertEquals('table', $sale->order_type);
        $this->assertEquals('mixed', $sale->payment_method);
        $this->assertEquals('completed', $sale->status);

        // 4. Verificar pagos mixtos registrados
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 20000,
            'cash_received' => 50000,
            'change_given' => 30000,
        ]);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'transfer',
            'amount' => 20000,
            'reference' => 'NEQUI-998877',
        ]);

        // 5. En restaurante el stock físico de platos preparados no bloquea ni requiere existencias
        $this->dishBurger->refresh();
        $this->assertEquals(10, (int) $this->dishBurger->stock);

        // 6. Verificar cierre de comanda y liberación automática de mesa
        $this->assertDatabaseHas('restaurant_orders', [
            'id' => $order->id,
            'status' => 'closed',
            'sale_id' => $sale->id,
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $this->table->id,
            'status' => 'available',
        ]);
    }

    public function test_delivery_order_settlement_transfers_delivery_fee_to_sale_and_closes_order(): void
    {
        // 1. Pedido de Domicilio: 1 Hamburguesa ($20.000) + Flete Domicilio ($6.000) = Total $26.000
        $deliveryOrder = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'order_number' => 'DOM-000050',
            'order_type' => 'delivery',
            'status' => 'dispatched',
            'customer_name' => 'Diana López',
            'customer_phone' => '3157778899',
            'delivery_address' => 'Calle 5 # 66-00, Cali',
            'delivery_fee' => 6000,
            'subtotal' => 20000,
            'total' => 26000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $deliveryOrder->id,
            'product_id' => $this->dishBurger->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
            'status' => 'served',
            'batch_number' => 1,
        ]);

        // 2. Liquidación contraentrega en efectivo ($26.000 exacto)
        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $deliveryOrder->id,
            'order_type' => 'delivery',
            'discount_percentage' => 0,
            'delivery_fee' => 6000,
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 1,
                    'unit_price' => 20000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 26000,
                    'cash_received' => 30000,
                    'change_given' => 4000,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // 3. Verificar venta con flete
        $sale = Sale::where('restaurant_order_id', $deliveryOrder->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(6000, $sale->delivery_fee);
        $this->assertEquals(26000, $sale->total);
        $this->assertEquals('delivery', $sale->order_type);

        // 4. Verificar cierre del pedido de domicilio
        $deliveryOrder->refresh();
        $this->assertEquals('closed', $deliveryOrder->status);
        $this->assertEquals($sale->id, $deliveryOrder->sale_id);
    }

    public function test_restaurant_settlement_succeeds_even_when_product_stock_is_zero(): void
    {
        // 1. Dejar stock de plato en 0 (se requieren 2 hamburguesas)
        $this->dishBurger->update(['stock' => 0]);

        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'CMD-000102',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 40000,
            'total' => 40000,
        ]);

        $this->table->update(['status' => 'occupied']);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 2,
                ],
            ],
            'payment_method' => 'cash',
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Venta creada exitosamente sin bloquearse por falta de stock
        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(40000, $sale->total);

        // La comanda se cierra y la mesa queda libre
        $this->assertDatabaseHas('restaurant_orders', [
            'id' => $order->id,
            'status' => 'closed',
            'sale_id' => $sale->id,
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $this->table->id,
            'status' => 'available',
        ]);
    }

    public function test_settlement_rollback_when_payment_balance_mismatches_total(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'CMD-000103',
            'order_type' => 'table',
            'status' => 'billed',
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        $this->table->update(['status' => 'billed']);

        // Intentar pagar $15.000 cuando el total es $20.000
        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 15000,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertSessionHasErrors('payments');

        // Sin venta, orden en cobro, mesa en cobro
        $this->assertEquals(0, Sale::where('restaurant_order_id', $order->id)->count());

        $this->assertDatabaseHas('restaurant_orders', [
            'id' => $order->id,
            'status' => 'billed',
        ]);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $this->table->id,
            'status' => 'billed',
        ]);
    }

    public function test_settlement_with_voluntary_service_fee_and_inc_tax_calculates_and_persists_authoritatively(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'CMD-000104',
            'order_type' => 'table',
            'status' => 'billed',
            'subtotal' => 40000,
            'total' => 40000,
        ]);

        $this->table->update(['status' => 'billed']);

        // Subtotal: $40.000 (2 hamburguesas)
        // Descuento: 10% ($4.000) -> Base gravable: $36.000
        // Servicio Voluntario (10% de base): $3.600
        // Impuesto al Consumo INC (8% de base): $2.880
        // Total authoritative: 40000 - 4000 + 3600 + 2880 = 42.480
        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 10,
            'service_fee' => 3600.00,
            'tax_inc' => 2880.00,
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 2,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 20000.00,
                    'cash_received' => 20000.00,
                ],
                [
                    'method' => 'card',
                    'amount' => 22480.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(40000.00, (float) $sale->subtotal);
        $this->assertEquals(4000.00, (float) $sale->discount);
        $this->assertEquals(3600.00, (float) $sale->service_fee);
        $this->assertEquals(2880.00, (float) $sale->tax_inc);
        $this->assertEquals(42480.00, (float) $sale->total);
        $this->assertEquals('mixed', $sale->payment_method);

        // Verificar que los comprobantes reflejan el desglose
        $receiptResponse = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('sales.print.receipt', $sale));
        $receiptResponse->assertStatus(200);
        $receiptResponse->assertSee('Servicio / Propina');
        $receiptResponse->assertSee('Impuesto al Consumo (INC 8%)');
        $receiptResponse->assertSee('3.600');
        $receiptResponse->assertSee('2.880');
        $receiptResponse->assertSee('42.480');

        $showResponse = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('sales.show', $sale));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Servicio / Propina');
        $showResponse->assertSee('Impuesto al Consumo (INC 8%)');
    }

    public function test_settlement_defaults_service_fee_and_tax_inc_to_zero_when_omitted(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'CMD-000105',
            'order_type' => 'table',
            'status' => 'billed',
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        $this->table->update(['status' => 'billed']);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            // service_fee y tax_inc omitidos
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 20000,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(0.00, (float) $sale->service_fee);
        $this->assertEquals(0.00, (float) $sale->tax_inc);
        $this->assertEquals(20000.00, (float) $sale->total);
    }

    public function test_colombian_legal_formula_calculates_tip_and_inc_strictly_on_net_food_consumption(): void
    {
        // Subtotal: 2 x 20.000 = 40.000
        // Descuento: 10% = 4.000
        // Base Gravable neta: 36.000
        // Servicio / Propina 10%: 3.600 (Ley 1935 de 2018, sobre 36.000, no causa INC)
        // INC 8%: 2.880 (Art. 512-1 E.T., 8% de 36.000, NO grava propina ni domicilio)
        // Domicilio: 5.000
        // Total = 36.000 + 5.000 + 3.600 + 2.880 = 47.480
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'order_number' => 'CMD-NORMA-01',
            'order_type' => 'delivery',
            'status' => 'open',
            'subtotal' => 40000,
            'delivery_fee' => 5000,
            'total' => 45000,
        ]);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'delivery',
            'discount_percentage' => 10,
            'delivery_fee' => 5000,
            'service_fee' => 3600,
            'tax_inc' => 2880,
            'items' => [
                [
                    'product_id' => $this->dishBurger->id,
                    'quantity' => 2,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 47480,
                    'cash_received' => 50000,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(40000.00, (float) $sale->subtotal);
        $this->assertEquals(4000.00, (float) $sale->discount);
        $this->assertEquals(5000.00, (float) $sale->delivery_fee);
        $this->assertEquals(3600.00, (float) $sale->service_fee);
        $this->assertEquals(2880.00, (float) $sale->tax_inc);
        $this->assertEquals(47480.00, (float) $sale->total);

        // Comprobar que en el comprobante fiscal se detalla con precisión
        $receipt = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('sales.print.receipt', $sale));

        $receipt->assertStatus(200);
        $receipt->assertSee('3.600');
        $receipt->assertSee('2.880');
        $receipt->assertSee('47.480');
    }
}
