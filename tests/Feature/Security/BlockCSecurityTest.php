<?php

namespace Tests\Feature\Security;

use App\Enums\PaymentMethod;
use App\Exceptions\Sales\InvalidSalePaymentException;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockCSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminA;
    protected User $employeeA;
    protected User $adminB;
    protected Product $productA;
    protected Product $productA2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Acceso y administración total']
        );

        $this->employeeRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Empleado', 'description' => 'Vendedor']
        );

        $this->businessA = Business::create([
            'name' => 'Comercial Cali A',
            'nit' => '900111222-1',
            'status' => 'active',
        ]);

        $this->businessB = Business::create([
            'name' => 'Comercial Cali B',
            'nit' => '900333444-2',
            'status' => 'active',
        ]);

        $this->adminA = User::factory()->create(['name' => 'Admin A', 'email' => 'adminA@cali.com']);
        $this->businessA->users()->attach($this->adminA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeA = User::factory()->create(['name' => 'Empleado A', 'email' => 'empleadoA@cali.com']);
        $this->businessA->users()->attach($this->employeeA->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->adminB = User::factory()->create(['name' => 'Admin B', 'email' => 'adminB@cali.com']);
        $this->businessB->users()->attach($this->adminB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Calzado',
        ]);

        $this->productA = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'category_id' => $category->id,
            'name' => 'Zapato Deportivo',
            'sku' => 'ZAP-001',
            'cost_price' => 50000,
            'sale_price' => 90000,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $this->productA2 = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'category_id' => $category->id,
            'name' => 'Cordones',
            'sku' => 'COR-001',
            'cost_price' => 2000,
            'sale_price' => 5000,
            'stock' => 100,
            'min_stock' => 10,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Pago simple en efectivo con cálculo autoritativo de vueltos.
     */
    public function test_single_cash_payment_calculates_change_authoritatively(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'customer_id' => null,
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1], // 90.000
                ],
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 90000,
                        'cash_received' => 100000,
                        'change_given' => 999999, // Intent de cliente ignorado
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $sale = Sale::latest()->first();
        $this->assertNotNull($sale);
        $this->assertEquals('cash', $sale->payment_method);
        $this->assertEquals(90000, $sale->total);

        $payment = $sale->payments()->first();
        $this->assertNotNull($payment);
        $this->assertEquals('cash', $payment->method->value);
        $this->assertEquals(90000, $payment->amount);
        $this->assertEquals(100000, $payment->cash_received);
        $this->assertEquals(10000, $payment->change_given, 'El vuelto debe ser exactamente 100.000 - 90.000 = 10.000');
        $this->assertEquals($this->businessA->id, $payment->business_id);
    }

    /**
     * 2. Pago mixto (ej. Transferencia/Nequi $50.000 + Efectivo $40.000 con billete de $50.000).
     */
    public function test_mixed_payment_is_persisted_with_mixed_sale_method(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'customer_id' => null,
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1], // Total 90.000
                ],
                'payments' => [
                    [
                        'method' => 'transfer',
                        'amount' => 50000,
                        'reference' => 'NEQUI-123456',
                    ],
                    [
                        'method' => 'cash',
                        'amount' => 40000,
                        'cash_received' => 50000,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $sale = Sale::withoutGlobalScopes()->latest()->first();
        $this->assertEquals('mixed', $sale->payment_method);
        $this->assertEquals('Pago mixto', $sale->payment_method_label);
        $this->assertCount(2, $sale->payments);

        $transferPayment = $sale->payments()->where('method', 'transfer')->first();
        $this->assertEquals(50000, $transferPayment->amount);
        $this->assertEquals('NEQUI-123456', $transferPayment->reference);
        $this->assertNull($transferPayment->cash_received);
        $this->assertNull($transferPayment->change_given);

        $cashPayment = $sale->payments()->where('method', 'cash')->first();
        $this->assertEquals(40000, $cashPayment->amount);
        $this->assertEquals(50000, $cashPayment->cash_received);
        $this->assertEquals(10000, $cashPayment->change_given);
    }

    /**
     * 3. Rechazar suma discrepante (suma de montos < total o > total).
     */
    public function test_rejects_payments_where_sum_does_not_match_total(): void
    {
        // Caso A: Suma menor al total (80.000 vs 90.000)
        $responseLess = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1], // 90.000
                ],
                'payments' => [
                    ['method' => 'transfer', 'amount' => 50000],
                    ['method' => 'cash', 'amount' => 30000, 'cash_received' => 30000],
                ],
            ]);

        $responseLess->assertSessionHasErrors(['payments']);

        // Caso B: Suma mayor al total (95.000 vs 90.000)
        $responseMore = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1], // 90.000
                ],
                'payments' => [
                    ['method' => 'card', 'amount' => 50000],
                    ['method' => 'cash', 'amount' => 45000, 'cash_received' => 45000],
                ],
            ]);

        $responseMore->assertSessionHasErrors(['payments']);
    }

    /**
     * 4. Rechazar más de una línea de efectivo (cash).
     */
    public function test_rejects_multiple_cash_payment_lines(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1], // 90.000
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 50000, 'cash_received' => 50000],
                    ['method' => 'cash', 'amount' => 40000, 'cash_received' => 40000],
                ],
            ]);

        $response->assertSessionHasErrors(['payments']);
    }

    /**
     * 5. Rechazar si cash_received es menor que el amount de la línea de efectivo.
     */
    public function test_rejects_cash_received_lower_than_amount(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1], // 90.000
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 90000, 'cash_received' => 80000],
                ],
            ]);

        $response->assertSessionHasErrors(['payments.0.cash_received']);
    }

    /**
     * 6. Rechazar montos negativos o en cero.
     */
    public function test_rejects_zero_or_negative_payment_amounts(): void
    {
        $responseZero = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 90000],
                    ['method' => 'transfer', 'amount' => 0],
                ],
            ]);

        $responseZero->assertSessionHasErrors(['payments.1.amount']);

        $responseNegative = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 100000],
                    ['method' => 'transfer', 'amount' => -10000],
                ],
            ]);

        $responseNegative->assertSessionHasErrors(['payments.1.amount']);
    }

    /**
     * 7. Sanitizar tags HTML / XSS en el campo reference.
     */
    public function test_sanitizes_xss_in_payment_reference(): void
    {
        $maliciousRef = '<script>alert("xss")</script>REF-7788';

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1],
                ],
                'payments' => [
                    [
                        'method' => 'transfer',
                        'amount' => 90000,
                        'reference' => $maliciousRef,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $sale = Sale::withoutGlobalScopes()->latest()->first();
        $payment = $sale->payments()->first();

        $this->assertStringNotContainsString('<script>', $payment->reference);
        $this->assertEquals('alert("xss")REF-7788', $payment->reference);
    }

    /**
     * 8. Rollback transaccional: si los pagos son inválidos en servicio, no se crea venta ni se descuenta stock.
     */
    public function test_transaction_rollback_preserves_stock_when_payment_fails(): void
    {
        $initialStock = $this->productA->stock; // 50
        $saleService = app(SaleService::class);

        $this->expectException(InvalidSalePaymentException::class);

        try {
            $saleService->processSale(
                data: [
                    'sale_date' => now()->format('Y-m-d H:i:s'),
                    'discount_percentage' => 0,
                    'items' => [
                        ['product_id' => $this->productA->id, 'quantity' => 5], // Total 450.000
                    ],
                    'payments' => [
                        ['method' => 'cash', 'amount' => 200000], // Falta dinero (200k != 450k)
                    ],
                ],
                businessId: $this->businessA->id,
                userId: $this->adminA->id,
            );
        } finally {
            // Verificar que no se creó ninguna venta ni detalle y el stock quedó en 50
            $this->assertEquals(0, Sale::withoutGlobalScopes()->count());
            $this->assertEquals($initialStock, $this->productA->fresh()->stock);
        }
    }

    /**
     * 9. Aislamiento multi-tenant en sale_payments: no se exponen pagos de otros tenants.
     */
    public function test_sale_payments_are_strictly_isolated_by_tenant(): void
    {
        // Venta en tenant A
        $saleA = Sale::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'FAC-A-001',
            'sale_date' => now(),
            'subtotal' => 90000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 90000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $paymentA = SalePayment::create([
            'business_id' => $this->businessA->id,
            'sale_id' => $saleA->id,
            'method' => 'cash',
            'amount' => 90000,
            'cash_received' => 100000,
            'change_given' => 10000,
        ]);

        // Venta en tenant B
        $saleB = Sale::create([
            'business_id' => $this->businessB->id,
            'user_id' => $this->adminB->id,
            'invoice_number' => 'FAC-B-001',
            'sale_date' => now(),
            'subtotal' => 90000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 90000,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);
        $paymentB = SalePayment::create([
            'business_id' => $this->businessB->id,
            'sale_id' => $saleB->id,
            'method' => 'card',
            'amount' => 90000,
        ]);

        // Consulta autenticado como tenant A activado en TenantManager
        app(\App\Services\Tenant\TenantManager::class)->set($this->businessA);

        $paymentsVisibleForA = SalePayment::all();
        $this->assertTrue($paymentsVisibleForA->contains('id', $paymentA->id));
        $this->assertFalse($paymentsVisibleForA->contains('id', $paymentB->id));

        // Intento de Tenant B de ver la venta A por HTTP: debe rechazar con 403 o 404 (aislamiento por TenantScope)
        $response = $this->actingAs($this->adminB)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->get(route('sales.show', $saleA));

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]), "El acceso cruzado debe ser 403 o 404, recibido {$response->getStatusCode()}");
    }

    /**
     * 10. Reportes calculan ingresos por método usando SUM(sale_payments.amount) y NUNCA cash_received.
     */
    public function test_reports_calculate_by_payment_method_using_amount_not_cash_received(): void
    {
        // Venta 1: Efectivo $40.000 (cliente pagó con billete de $100.000, vuelto $60.000)
        // Venta 2: Mixta $90.000 (Nequi $50.000 + Efectivo $40.000 pagado con billete de $50.000, vuelto $10.000)
        $saleService = app(SaleService::class);

        $saleService->processSale(
            data: [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA2->id, 'quantity' => 8], // 8 * 5.000 = 40.000
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 40000, 'cash_received' => 100000],
                ],
            ],
            businessId: $this->businessA->id,
            userId: $this->adminA->id,
        );

        $saleService->processSale(
            data: [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'discount_percentage' => 0,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1], // 90.000
                ],
                'payments' => [
                    ['method' => 'transfer', 'amount' => 50000, 'reference' => 'TR-999'],
                    ['method' => 'cash', 'amount' => 40000, 'cash_received' => 50000],
                ],
            ],
            businessId: $this->businessA->id,
            userId: $this->adminA->id,
        );

        $reportService = app(ReportService::class);
        $report = $reportService->getSalesReport($this->businessA->id, [
            'date_from' => now()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        $byMethod = $report['by_payment_method'];

        $cashRow = $byMethod->firstWhere('method', 'cash');
        $transferRow = $byMethod->firstWhere('method', 'transfer');

        // En efectivo, el monto real ingresado es 40.000 + 40.000 = 80.000 (NUNCA 100.000 + 50.000 = 150.000 de cash_received)
        $this->assertNotNull($cashRow);
        $this->assertEquals(80000, $cashRow['total']);

        $this->assertNotNull($transferRow);
        $this->assertEquals(50000, $transferRow['total']);

        $this->assertEquals(130000, $report['total_revenue']);
    }

    /**
     * 11. Retrocompatibilidad: Si un cliente antiguo envía solo 'payment_method', se crea el sale_payment automáticamente.
     */
    public function test_legacy_payload_with_single_payment_method_creates_sale_payment(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'customer_id' => null,
                'discount_percentage' => 0,
                'payment_method' => 'card', // Legacy payload
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1], // 90.000
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $sale = Sale::latest()->first();
        $this->assertEquals('card', $sale->payment_method);
        $this->assertCount(1, $sale->payments);

        $payment = $sale->payments()->first();
        $this->assertEquals('card', $payment->method->value);
        $this->assertEquals(90000, $payment->amount);
        $this->assertNull($payment->cash_received);
    }

    /**
     * 12. Idempotencia del backfill: Si se ejecuta la migración en una base con ventas sin pagos, se generan sin duplicar.
     */
    public function test_idempotent_backfill_creates_missing_payments_without_duplicates(): void
    {
        $sale = Sale::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'FAC-OLD-001',
            'sale_date' => now(),
            'subtotal' => 50000,
            'discount' => 0,
            'discount_percentage' => 0,
            'total' => 50000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);

        // Simular ejecución del backfill
        $salesWithoutPayments = Sale::withoutGlobalScopes()
            ->whereDoesntHave('payments')
            ->where('total', '>', 0)
            ->get();

        foreach ($salesWithoutPayments as $s) {
            SalePayment::create([
                'business_id' => $s->business_id,
                'sale_id' => $s->id,
                'method' => in_array($s->payment_method, ['cash', 'card', 'transfer', 'other']) ? $s->payment_method : 'other',
                'amount' => $s->total,
                'cash_received' => $s->payment_method === 'cash' ? $s->total : null,
                'change_given' => $s->payment_method === 'cash' ? 0.00 : null,
            ]);
        }

        $this->assertEquals(1, $sale->payments()->count());
        $this->assertEquals(50000, $sale->payments()->first()->amount);

        // Volver a ejecutar el query de backfill: debe ser 0 filas
        $remaining = Sale::withoutGlobalScopes()
            ->whereDoesntHave('payments')
            ->where('total', '>', 0)
            ->count();

        $this->assertEquals(0, $remaining, 'El backfill debe ser totalmente idempotente');
    }
}
