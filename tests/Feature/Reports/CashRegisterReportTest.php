<?php

namespace Tests\Feature\Reports;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Expense;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterReportTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminA;
    protected User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->businessA = Business::create([
            'name' => 'Comercio A Cali',
            'business_type' => 'retail',
        ]);

        $this->businessB = Business::create([
            'name' => 'Comercio B Cali',
            'business_type' => 'retail',
        ]);

        $this->adminA = User::factory()->create(['name' => 'Admin Negocio A']);
        $this->businessA->users()->attach($this->adminA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->adminB = User::factory()->create(['name' => 'Admin Negocio B']);
        $this->businessB->users()->attach($this->adminB->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_cash_expenses_reduce_caja_menor_physical_cash_balance(): void
    {
        $today = Carbon::today()->setTime(10, 0);

        // Venta en efectivo de $100.000
        $sale = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'VTA-202610-0001',
            'sale_date' => $today,
            'subtotal' => 100000,
            'total' => 100000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SalePayment::create([
            'business_id' => $this->businessA->id,
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 100000,
            'cash_received' => 100000,
            'change_given' => 0,
        ]);

        // Gasto operativo pagado en efectivo de $30.000 (Caja Menor)
        Expense::create([
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
            'invoice_number' => 'GAS-001',
            'issue_date' => $today->format('Y-m-d'),
            'paid_at' => $today->format('Y-m-d'),
            'category' => ExpenseCategory::Supplies,
            'description' => 'Compra de cinta y bolsas en efectivo',
            'amount' => 30000,
            'status' => 'paid',
            'payment_method' => PaymentMethod::Cash,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('reports.cash-register', ['date' => $today->format('Y-m-d')]));

        $response->assertStatus(200);

        // Validaciones en vista y balance
        $response->assertSee('Arqueo de Caja Menor');
        $response->assertSee('Total Efectivo a Entregar:');
        $response->assertSee('$70.000'); // $100.000 ventas - $30.000 gastos = $70.000 neto
        $response->assertSee('GAS-001');
        $response->assertSee('Compra de cinta y bolsas en efectivo');
    }

    public function test_transfer_expenses_reduce_caja_general_digital_accounts_balance(): void
    {
        $today = Carbon::today()->setTime(11, 0);

        // Venta por transferencia de $80.000
        $sale = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'VTA-202610-0002',
            'sale_date' => $today,
            'subtotal' => 80000,
            'total' => 80000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);

        SalePayment::create([
            'business_id' => $this->businessA->id,
            'sale_id' => $sale->id,
            'method' => 'transfer',
            'amount' => 80000,
            'reference' => 'NEQUI-112233',
        ]);

        // Gasto operativo pagado por transferencia de $25.000 (Caja General)
        Expense::create([
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
            'invoice_number' => 'GAS-002',
            'issue_date' => $today->format('Y-m-d'),
            'paid_at' => $today->format('Y-m-d'),
            'category' => ExpenseCategory::Utilities,
            'description' => 'Pago internet fibra por Nequi',
            'amount' => 25000,
            'status' => 'paid',
            'payment_method' => PaymentMethod::Transfer,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('reports.cash-register', ['date' => $today->format('Y-m-d')]));

        $response->assertStatus(200);

        $response->assertSee('Caja General');
        $response->assertSee('Saldo en Cuentas Digitales:');
        $response->assertSee('$55.000'); // $80.000 ventas digital - $25.000 gasto digital = $55.000
        $response->assertSee('GAS-002');
    }

    public function test_net_turn_balance_consolidates_total_sales_and_all_expenses(): void
    {
        $today = Carbon::today()->setTime(12, 0);

        // $100.000 efectivo + $80.000 transferencia = $180.000 recaudado
        $sale1 = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'VTA-202610-0003',
            'sale_date' => $today,
            'subtotal' => 100000,
            'total' => 100000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        SalePayment::create([
            'business_id' => $this->businessA->id,
            'sale_id' => $sale1->id,
            'method' => 'cash',
            'amount' => 100000,
        ]);

        $sale2 = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'VTA-202610-0004',
            'sale_date' => $today,
            'subtotal' => 80000,
            'total' => 80000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);
        SalePayment::create([
            'business_id' => $this->businessA->id,
            'sale_id' => $sale2->id,
            'method' => 'transfer',
            'amount' => 80000,
        ]);

        // Gastos: $30.000 efectivo + $20.000 transferencia = $50.000 total gastos
        Expense::create([
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
            'invoice_number' => 'GAS-EFE-01',
            'issue_date' => $today->format('Y-m-d'),
            'paid_at' => $today->format('Y-m-d'),
            'category' => ExpenseCategory::Other,
            'description' => 'Salida menor de caja',
            'amount' => 30000,
            'status' => 'paid',
            'payment_method' => PaymentMethod::Cash,
        ]);

        Expense::create([
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
            'invoice_number' => 'GAS-TRA-01',
            'issue_date' => $today->format('Y-m-d'),
            'paid_at' => $today->format('Y-m-d'),
            'category' => ExpenseCategory::Rent,
            'description' => 'Abono arriendo por banco',
            'amount' => 20000,
            'status' => 'paid',
            'payment_method' => PaymentMethod::Transfer,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('reports.cash-register', ['date' => $today->format('Y-m-d')]));

        $response->assertStatus(200);

        // $180.000 recaudado - $50.000 egresos = $130.000 balance neto
        $response->assertSee('Balance Consolidado');
        $response->assertSee('Balance Neto del Período:');
        $response->assertSee('$130.000');
    }

    public function test_expenses_in_cash_register_are_strictly_isolated_by_tenant(): void
    {
        $today = Carbon::today()->setTime(14, 0);

        // Venta en Negocio A
        $saleA = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'VTA-TENANT-A',
            'sale_date' => $today,
            'subtotal' => 50000,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        SalePayment::create([
            'business_id' => $this->businessA->id,
            'sale_id' => $saleA->id,
            'method' => 'cash',
            'amount' => 50000,
        ]);

        // Gasto registrado en Negocio B
        Expense::create([
            'business_id' => $this->businessB->id,
            'created_by' => $this->adminB->id,
            'invoice_number' => 'EXP-TENANT-B',
            'issue_date' => $today->format('Y-m-d'),
            'paid_at' => $today->format('Y-m-d'),
            'category' => ExpenseCategory::Payroll,
            'description' => 'Gasto confidencial de Negocio B',
            'amount' => 45000,
            'status' => 'paid',
            'payment_method' => PaymentMethod::Cash,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('reports.cash-register', ['date' => $today->format('Y-m-d')]));

        $response->assertStatus(200);

        // Negocio A no debe ver ni verse afectado por el gasto de Negocio B
        $response->assertDontSee('EXP-TENANT-B');
        $response->assertDontSee('Gasto confidencial de Negocio B');
        $response->assertSee('$50.000'); // Efectivo neto completo intacto
    }

    public function test_pending_expenses_are_excluded_from_cash_register_balance(): void
    {
        $today = Carbon::today()->setTime(15, 0);

        $sale = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'VTA-PEND-01',
            'sale_date' => $today,
            'subtotal' => 60000,
            'total' => 60000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        SalePayment::create([
            'business_id' => $this->businessA->id,
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 60000,
        ]);

        // Gasto con estado pendiente (aún no se ha pagado / retirado el dinero)
        Expense::create([
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
            'invoice_number' => 'EXP-PENDING-01',
            'issue_date' => $today->format('Y-m-d'),
            'paid_at' => null,
            'category' => ExpenseCategory::Utilities,
            'description' => 'Recibo de luz pendiente de pago',
            'amount' => 20000,
            'status' => 'pending',
            'payment_method' => PaymentMethod::Cash,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('reports.cash-register', ['date' => $today->format('Y-m-d')]));

        $response->assertStatus(200);

        // No debe deducir el gasto pendiente de la gaveta de caja
        $response->assertDontSee('EXP-PENDING-01');
        $response->assertSee('$60.000');
    }

    public function test_cash_register_csv_export_includes_expenses_and_mitigates_formula_injection(): void
    {
        $today = Carbon::today()->setTime(16, 0);

        // Gasto con posible fórmula maliciosa en descripción (CWE-1236)
        Expense::create([
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
            'invoice_number' => '=1+1',
            'issue_date' => $today->format('Y-m-d'),
            'paid_at' => $today->format('Y-m-d'),
            'category' => ExpenseCategory::Other,
            'description' => '@SUM(1,2)',
            'amount' => 15000,
            'status' => 'paid',
            'payment_method' => PaymentMethod::Cash,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('reports.cash-register', [
                'date' => $today->format('Y-m-d'),
                'export' => 'csv',
            ]));

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();

        // Debe contener secciones de Caja Menor y Egresos
        $this->assertStringContainsString('CUADRE DE CAJA', $content);
        $this->assertStringContainsString('Caja Menor (Efectivo Neto Esperado)', $content);
        $this->assertStringContainsString('Total Egresos (Gastos Operativos)', $content);

        // Mitigación de inyección de fórmulas (apóstrofe antepuesto)
        $this->assertStringContainsString("'=1+1", $content);
        $this->assertStringContainsString("'@SUM(1,2)", $content);
    }
}
