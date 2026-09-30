<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantCashRegisterReportTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurantA;
    protected Business $restaurantB;
    protected Business $retailBusiness;
    protected User $adminA;
    protected User $employeeA;
    protected User $retailAdmin;

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
            'name' => 'Asadero Las Brisas Cali A',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantB = Business::create([
            'name' => 'Asadero Las Brisas B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailBusiness = Business::create([
            'name' => 'Ferretería El Tornillo',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create();
        $this->restaurantA->users()->attach($this->adminA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeA = User::factory()->create();
        $this->restaurantA->users()->attach($this->employeeA->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->retailAdmin = User::factory()->create();
        $this->retailBusiness->users()->attach($this->retailAdmin->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);
    }

    public function test_restaurant_cash_register_discriminates_sales_channels_and_reconciles_cash_and_digital(): void
    {
        $today = Carbon::today()->setTime(12, 0);

        // 1. Venta de Salón: $80.000 (Pagada en Efectivo $100.000, vuelto $20.000 -> Neto efectivo $80.000)
        $saleTable = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'FAC-000001',
            'order_type' => 'table',
            'delivery_fee' => 0,
            'sale_date' => $today,
            'subtotal' => 80000,
            'total' => 80000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SalePayment::create([
            'business_id' => $this->restaurantA->id,
            'sale_id' => $saleTable->id,
            'method' => 'cash',
            'amount' => 80000,
            'cash_received' => 100000,
            'change_given' => 20000,
        ]);

        // 2. Venta de Domicilio: $45.000 de platos + $5.000 flete = Total $50.000 (Pagada por Nequi / Transferencia)
        $saleDelivery = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'FAC-000002',
            'order_type' => 'delivery',
            'delivery_fee' => 5000,
            'sale_date' => $today,
            'subtotal' => 45000,
            'total' => 50000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);

        SalePayment::create([
            'business_id' => $this->restaurantA->id,
            'sale_id' => $saleDelivery->id,
            'method' => 'transfer',
            'amount' => 50000,
            'reference' => 'NEQUI-112233',
        ]);

        // 3. Venta Para Llevar: $25.000 (Pagada con Tarjeta)
        $saleTakeout = Sale::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'FAC-000003',
            'order_type' => 'takeout',
            'delivery_fee' => 0,
            'sale_date' => $today,
            'subtotal' => 25000,
            'total' => 25000,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);

        SalePayment::create([
            'business_id' => $this->restaurantA->id,
            'sale_id' => $saleTakeout->id,
            'method' => 'card',
            'amount' => 25000,
            'reference' => 'DATAFONO-4455',
        ]);

        // Consultar el reporte de cuadre de caja
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->get(route('reports.cash-register', ['date' => $today->format('Y-m-d')]));

        $response->assertStatus(200);

        // Validar canales de venta
        $response->assertSee('Discriminación por Canal de Venta');
        $response->assertSee('Ventas Salón (Mesas)');
        $response->assertSee('$80.000');
        $response->assertSee('Ventas Domicilios');
        $response->assertSee('$50.000');
        $response->assertSee('Ventas Para Llevar');
        $response->assertSee('$25.000');

        // Validar totalización de fletes para repartidores
        $response->assertSee('Recaudo Total por Fletes / Domicilios');
        $response->assertSee('$5.000');

        // Validar conciliación: Efectivo en gaveta ($80.000) vs Digital ($50.000 + $25.000 = $75.000)
        $response->assertSee('Efectivo en Gaveta');
        $response->assertSee('Dinero Digital (Apps / Bancos)');
        $response->assertSee('$75.000');
        $response->assertSee('Total Conciliado');
        $response->assertSee('$155.000');
    }

    public function test_retail_business_accesses_traditional_cash_register_without_restaurant_sections(): void
    {
        $today = Carbon::today()->setTime(11, 0);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->retailBusiness->id,
            'user_id' => $this->retailAdmin->id,
            'invoice_number' => 'FAC-RETAIL-01',
            'order_type' => 'retail',
            'sale_date' => $today,
            'subtotal' => 60000,
            'total' => 60000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('reports.cash-register', ['date' => $today->format('Y-m-d')]));

        $response->assertStatus(200);

        // Debe ver el cuadre tradicional
        $response->assertSee('Cuadre de Caja');
        $response->assertSee('$60.000');
        $response->assertSee('FAC-RETAIL-01');

        // NO debe ver componentes de restaurantes
        $response->assertDontSee('Vertical Restaurante');
        $response->assertDontSee('Discriminación por Canal de Venta');
        $response->assertDontSee('Ventas Salón (Mesas)');
        $response->assertDontSee('Recaudo Total por Fletes / Domicilios');
    }

    public function test_dashboard_shows_operational_cards_for_restaurant(): void
    {
        // 1. Dashboard en Restaurante A
        $responseA = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->restaurantA->id])
            ->get(route('dashboard'));

        $responseA->assertStatus(200);
        $responseA->assertSee('Mesas Activas / Ocupadas');
        $responseA->assertSee('Domicilios en Curso');

        // 2. Dashboard en Comercio Retail
        $responseRetail = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('dashboard'));

        $responseRetail->assertStatus(200);
        $responseRetail->assertDontSee('Mesas Activas / Ocupadas');
        $responseRetail->assertDontSee('Domicilios en Curso');
    }
}
