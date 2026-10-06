<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected User $admin;
    protected User $waiter;
    protected RestaurantTable $table;
    protected Product $dishSteak;

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
            'name' => 'Asador Gourmet Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Cajero Principal']);
        $this->restaurant->users()->attach($this->admin->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->waiter = User::factory()->create(['name' => 'Mesero Andres']);
        $this->restaurant->users()->attach($this->waiter->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa VIP 1',
            'capacity' => 6,
            'status' => 'occupied',
        ]);

        $category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Cortes Premium',
        ]);

        // Plato del menú: Tomahawk Especial ($130.000 x 5 = $650.000)
        $this->dishSteak = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Tomahawk Steak Angus',
            'sku' => 'PLT-TOMAHAWK-01',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 70000,
            'sale_price' => 130000,
            'stock' => 20,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Caso 1: Cobro exitoso de una comanda de $650.000 con un solo método en Efectivo por $650.000 exactos.
     * Verifica cambio de estado a pagado/cerrado, liberación de mesa y generación de factura fiscal.
     */
    public function test_case_1_successful_settlement_of_650k_order_with_single_exact_cash_payment(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-0005',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 650000,
            'total' => 650000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dishSteak->id,
            'quantity' => 5,
            'unit_price' => 130000,
            'subtotal' => 650000,
            'status' => 'served',
            'batch_number' => 1,
        ]);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'service_fee' => 0,
            'tax_inc' => 0,
            'items' => [
                [
                    'product_id' => $this->dishSteak->id,
                    'quantity' => 5,
                    'unit_price' => 130000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 650000,
                    'cash_received' => 650000,
                    'change_given' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // 1. Verificar generación de la venta y consecutivo
        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(650000, (float) $sale->total);
        $this->assertEquals('cash', $sale->payment_method);
        $this->assertEquals('completed', $sale->status);
        $this->assertMatchesRegularExpression('/^VTA-\d{6}-\d{4}$/', $sale->invoice_number);

        // 2. Verificar pago de $650.000 registrado en base de datos
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 650000,
            'cash_received' => 650000,
            'change_given' => 0,
        ]);

        // 3. Verificar comanda cerrada y mesa liberada
        $order->refresh();
        $this->assertEquals('closed', $order->status);
        $this->assertEquals($sale->id, $order->sale_id);
        $this->assertNotNull($order->closed_at);

        $this->table->refresh();
        $this->assertEquals('available', $this->table->status);
    }

    /**
     * Caso 2: Cobro exitoso con pago dividido ($400.000 Efectivo + $250.000 Transferencia).
     */
    public function test_case_2_successful_settlement_with_split_payment_cash_and_transfer(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-0006',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 650000,
            'total' => 650000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dishSteak->id,
            'quantity' => 5,
            'unit_price' => 130000,
            'subtotal' => 650000,
            'status' => 'served',
            'batch_number' => 1,
        ]);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'items' => [
                [
                    'product_id' => $this->dishSteak->id,
                    'quantity' => 5,
                    'unit_price' => 130000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 400000,
                    'cash_received' => 400000,
                    'change_given' => 0,
                ],
                [
                    'method' => 'transfer',
                    'amount' => 250000,
                    'reference' => 'NEQUI-123456',
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
        $this->assertEquals(650000, (float) $sale->total);
        $this->assertEquals('mixed', $sale->payment_method);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 400000,
        ]);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'transfer',
            'amount' => 250000,
            'reference' => 'NEQUI-123456',
        ]);
    }

    /**
     * Caso 3: Cobro con propina (10%) e impuesto al consumo (INC 8%) activados,
     * verificando que los métodos de pago cubran el total global exacto:
     * Subtotal: $650.000
     * Propina 10%: $65.000
     * INC 8%: $52.000
     * Total: $767.000
     */
    public function test_case_3_successful_settlement_with_tip_and_inc_tax_activated(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-0007',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 650000,
            'total' => 650000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dishSteak->id,
            'quantity' => 5,
            'unit_price' => 130000,
            'subtotal' => 650000,
            'status' => 'served',
            'batch_number' => 1,
        ]);

        $subtotal = 650000;
        $tip = 65000; // 10%
        $taxInc = 52000; // 8% de $650.000
        $grandTotal = $subtotal + $tip + $taxInc; // $767.000

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'service_fee' => $tip,
            'tax_inc' => $taxInc,
            'items' => [
                [
                    'product_id' => $this->dishSteak->id,
                    'quantity' => 5,
                    'unit_price' => 130000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'card',
                    'amount' => $grandTotal,
                    'reference' => 'VISA-7890',
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
        $this->assertEquals($grandTotal, (float) $sale->total);
        $this->assertEquals($tip, (float) $sale->service_fee);
        $this->assertEquals($taxInc, (float) $sale->tax_inc);
        $this->assertEquals('card', $sale->payment_method);
    }

    /**
     * Caso 4: Validación de rechazo cuando la suma intencionalmente difiere del total.
     */
    public function test_case_4_rejection_when_payments_sum_differs_from_sale_total(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-0008',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 650000,
            'total' => 650000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dishSteak->id,
            'quantity' => 5,
            'unit_price' => 130000,
            'subtotal' => 650000,
            'status' => 'served',
            'batch_number' => 1,
        ]);

        // Se envían $600.000 cuando el total es $650.000
        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'items' => [
                [
                    'product_id' => $this->dishSteak->id,
                    'quantity' => 5,
                    'unit_price' => 130000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 600000,
                    'cash_received' => 600000,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('sales.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors('payments');

        $this->assertDatabaseMissing('sales', [
            'restaurant_order_id' => $order->id,
        ]);
    }

    /**
     * Caso 5: Sanitización robusta de cadenas con formato de moneda ($650.000, 650000,00, comas vs puntos).
     */
    public function test_case_5_currency_string_sanitization_is_parsed_canonically(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-0009',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 650000,
            'total' => 650000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dishSteak->id,
            'quantity' => 5,
            'unit_price' => 130000,
            'subtotal' => 650000,
            'status' => 'served',
            'batch_number' => 1,
        ]);

        // Array con strings formateados con comas, puntos de miles o símbolo $
        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'discount_percentage' => 0,
            'delivery_fee' => '$0.00',
            'service_fee' => '0,00',
            'tax_inc' => '0.00',
            'items' => [
                [
                    'product_id' => $this->dishSteak->id,
                    'quantity' => 5,
                    'unit_price' => 130000,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => '$650.000', // Con punto de miles y símbolo $
                    'cash_received' => '700.000', // Con punto de miles
                    'change_given' => '50000,00',
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
        $this->assertEquals(650000, (float) $sale->total);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 650000,
            'cash_received' => 700000,
            'change_given' => 50000,
        ]);
    }
}
