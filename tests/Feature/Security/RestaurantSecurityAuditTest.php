<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $tenantA;
    protected Business $tenantB;
    protected User $adminA;
    protected User $employeeA;
    protected User $adminB;
    protected User $superadmin;
    protected RestaurantTable $tableA;
    protected RestaurantTable $tableB;
    protected Product $dishA;
    protected Product $dishB;
    protected RestaurantOrder $orderA;
    protected RestaurantOrder $orderB;

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

        $this->tenantA = Business::create([
            'name' => 'Restaurante Cali Norte',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->tenantB = Business::create([
            'name' => 'Restaurante Cali Sur',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create(['name' => 'Admin Tenant A']);
        $this->tenantA->users()->attach($this->adminA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employeeA = User::factory()->create(['name' => 'Mesero Tenant A']);
        $this->tenantA->users()->attach($this->employeeA->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->adminB = User::factory()->create(['name' => 'Admin Tenant B']);
        $this->tenantB->users()->attach($this->adminB->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->superadmin = User::factory()->create([
            'name' => 'Super Administrador',
            'is_superadmin' => true,
        ]);

        $this->tableA = RestaurantTable::create([
            'business_id' => $this->tenantA->id,
            'name' => 'Mesa 1-A',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->tableB = RestaurantTable::create([
            'business_id' => $this->tenantB->id,
            'name' => 'Mesa 1-B',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->dishA = Product::create([
            'business_id' => $this->tenantA->id,
            'name' => 'Arroz Atollado A',
            'sku' => 'PLT-001-A',
            'sale_price' => 25000,
            'stock' => 100,
            'product_type' => 'dish',
            'is_active' => true,
        ]);

        $this->dishB = Product::create([
            'business_id' => $this->tenantB->id,
            'name' => 'Chuleta Valluna B',
            'sku' => 'PLT-001-B',
            'sale_price' => 30000,
            'stock' => 100,
            'product_type' => 'dish',
            'is_active' => true,
        ]);

        $this->orderA = RestaurantOrder::create([
            'business_id' => $this->tenantA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->adminA->id,
            'order_number' => 'ORD-0001',
            'order_type' => 'table',
            'status' => 'open',
            'customer_name' => 'Cliente A',
            'guest_count' => 2,
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        $this->orderB = RestaurantOrder::create([
            'business_id' => $this->tenantB->id,
            'table_id' => $this->tableB->id,
            'user_id' => $this->adminB->id,
            'order_number' => 'ORD-0002',
            'order_type' => 'table',
            'status' => 'open',
            'customer_name' => 'Cliente B',
            'guest_count' => 4,
            'subtotal' => 30000,
            'total' => 30000,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 1. MULTI-TENANT ISOLATION & CROSS-TENANT ATTACKS (CWE-200 / CWE-284)
    |--------------------------------------------------------------------------
    */

    public function test_cross_tenant_access_to_orders_routes_is_strictly_blocked(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        // Intentar ver comanda del Tenant B
        $this->get(route('restaurant.orders.show', $this->orderB))->assertNotFound();

        // Intentar imprimir comanda de cocina del Tenant B
        $this->get(route('restaurant.orders.kitchen-ticket', $this->orderB))->assertNotFound();

        // Intentar imprimir pre-cuenta del Tenant B
        $this->get(route('restaurant.orders.prebill', $this->orderB))->assertNotFound();

        // Intentar imprimir tirilla de despacho del Tenant B
        $this->get(route('restaurant.orders.dispatch-ticket', $this->orderB))->assertNotFound();

        // Intentar agregar platos a comanda del Tenant B
        $this->post(route('restaurant.orders.items.store', $this->orderB), [
            'items' => [
                ['product_id' => $this->dishA->id, 'quantity' => 1],
            ],
        ])->assertNotFound();

        // Intentar cancelar comanda del Tenant B
        $this->post(route('restaurant.orders.cancel-empty', $this->orderB))->assertNotFound();

        // Intentar cambiar estado operativo del Tenant B
        $this->post(route('restaurant.orders.status.update', $this->orderB), [
            'status' => 'in_kitchen',
        ])->assertNotFound();
    }

    public function test_cross_tenant_table_tampering_is_strictly_blocked(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        // Intentar editar mesa de Tenant B
        $this->get(route('restaurant.tables.edit', $this->tableB))->assertNotFound();

        // Intentar actualizar mesa de Tenant B
        $this->put(route('restaurant.tables.update', $this->tableB), [
            'name' => 'Mesa Hackeada',
            'capacity' => 10,
        ])->assertNotFound();

        // Intentar eliminar mesa de Tenant B
        $this->delete(route('restaurant.tables.destroy', $this->tableB))->assertNotFound();
    }

    public function test_customer_search_strictly_isolates_tenants_and_escapes_wildcards(): void
    {
        Customer::create([
            'business_id' => $this->tenantA->id,
            'name' => 'Carlos Mendoza % Tenant A',
            'document' => '1111222333',
            'phone' => '3001112233',
            'is_active' => true,
        ]);

        Customer::create([
            'business_id' => $this->tenantB->id,
            'name' => 'Carlos Mendoza % Tenant B',
            'document' => '4444555666',
            'phone' => '3004445566',
            'is_active' => true,
        ]);

        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        // Búsqueda normal: solo retorna cliente de Tenant A
        $response = $this->getJson(route('customers.search', ['q' => 'Carlos']));
        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Carlos Mendoza % Tenant A']);
        $response->assertJsonMissing(['name' => 'Carlos Mendoza % Tenant B']);

        // Búsqueda con comodines SQL ('%', '_', '!'):
        $wildcardResponse = $this->getJson(route('customers.search', ['q' => '%']));
        $wildcardResponse->assertOk();
        // Menor a 3 caracteres devuelve array vacío
        $this->assertEquals([], $wildcardResponse->json());

        $wildcardQueryResponse = $this->getJson(route('customers.search', ['q' => 'Mendoza %']));
        $wildcardQueryResponse->assertOk();
        $wildcardQueryResponse->assertJsonCount(1);
        $wildcardQueryResponse->assertJsonFragment(['name' => 'Carlos Mendoza % Tenant A']);
    }

    public function test_cannot_settle_sale_with_other_tenant_restaurant_order(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'restaurant_order_id' => $this->orderB->id, // Perteneciente al Tenant B
            'order_type' => 'table',
            'discount_percentage' => 0,
            'items' => [
                ['product_id' => $this->dishA->id, 'quantity' => 1],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 25000, 'cash_received' => 25000],
            ],
        ];

        $response = $this->post(route('sales.store'), $payload);
        $response->assertSessionHasErrors('restaurant_order_id');
    }

    /*
    |--------------------------------------------------------------------------
    | 2. RBAC & PRIVILEGE ESCALATION (CWE-269 / CWE-285)
    |--------------------------------------------------------------------------
    */

    public function test_ensure_user_is_superadmin_blocks_regular_user_with_404(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        $this->get('/superadmin')->assertNotFound();
        $this->get('/superadmin/businesses')->assertNotFound();
    }

    public function test_superadmin_is_redirected_away_from_tenant_operational_routes(): void
    {
        $this->actingAs($this->superadmin);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('superadmin.dashboard'));

        $jsonResponse = $this->getJson(route('dashboard'));
        $jsonResponse->assertStatus(403);
    }

    public function test_superadmin_collaborator_creation_immune_to_is_superadmin_mass_assignment(): void
    {
        $this->actingAs($this->superadmin);

        $payload = [
            'name' => 'Hacker Colaborador',
            'email' => 'hacker@tenant-a.com',
            'role' => 'employee',
            'password' => 'Password123#',
            'is_superadmin' => 1, // Intento de inyección de privilegio global
            'must_change_password' => 0,
        ];

        $response = $this->post(route('superadmin.businesses.users.store', $this->tenantA), $payload);
        $response->assertRedirect(route('superadmin.businesses.users', $this->tenantA));

        $createdUser = User::where('email', 'hacker@tenant-a.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertFalse((bool) $createdUser->is_superadmin, 'El usuario colaborador NUNCA debe ser superadmin.');
        $this->assertTrue((bool) $createdUser->must_change_password, 'El nuevo usuario DEBE tener must_change_password activo.');
    }

    public function test_superadmin_collaborator_creation_rejects_superadmin_role(): void
    {
        $this->actingAs($this->superadmin);

        $payload = [
            'name' => 'Falso Super',
            'email' => 'falso@tenant-a.com',
            'role' => 'superadmin', // Rol no permitido
        ];

        $response = $this->post(route('superadmin.businesses.users.store', $this->tenantA), $payload);
        $response->assertSessionHasErrors('role');
    }

    public function test_user_with_must_change_password_is_forced_by_middleware(): void
    {
        $newUser = User::factory()->create([
            'must_change_password' => true,
        ]);
        $this->tenantA->users()->attach($newUser->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($newUser)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        // Intentar navegar al dashboard o a cualquier ruta operativa
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('password.change'));

        $ordersResponse = $this->get(route('restaurant.orders.index'));
        $ordersResponse->assertRedirect(route('password.change'));

        $jsonResponse = $this->getJson(route('dashboard'));
        $jsonResponse->assertStatus(403);
    }

    public function test_employee_role_receives_403_on_admin_management_actions(): void
    {
        $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        $category = Category::create([
            'business_id' => $this->tenantA->id,
            'name' => 'Bebidas A',
        ]);

        // 1. Platos / Productos: Crear, Editar, Eliminar
        $this->get(route('products.create'))->assertForbidden();
        $this->post(route('products.store'), ['name' => 'Plato Prohibido', 'sale_price' => 10000])->assertForbidden();
        $this->get(route('products.edit', $this->dishA))->assertForbidden();
        $this->put(route('products.update', $this->dishA), ['name' => 'Modificado', 'sale_price' => 12000])->assertForbidden();
        $this->delete(route('products.destroy', $this->dishA))->assertForbidden();

        // 2. Categorías: Crear, Editar, Eliminar
        $this->get(route('categories.create'))->assertForbidden();
        $this->post(route('categories.store'), ['name' => 'Cat Hack'])->assertForbidden();
        $this->get(route('categories.edit', $category))->assertForbidden();
        $this->put(route('categories.update', $category), ['name' => 'Cat Mod'])->assertForbidden();
        $this->delete(route('categories.destroy', $category))->assertForbidden();

        // 3. Mesas: Crear, Editar, Eliminar
        $this->get(route('restaurant.tables.create'))->assertForbidden();
        $this->post(route('restaurant.tables.store'), ['name' => 'Mesa Hack'])->assertForbidden();
        $this->get(route('restaurant.tables.edit', $this->tableA))->assertForbidden();
        $this->put(route('restaurant.tables.update', $this->tableA), ['name' => 'Mesa Mod'])->assertForbidden();
        $this->delete(route('restaurant.tables.destroy', $this->tableA))->assertForbidden();

        // 4. Gastos: Acceso completo denegado
        $this->get(route('expenses.index'))->assertForbidden();
        $this->get(route('expenses.create'))->assertForbidden();
        $this->post(route('expenses.store'), [])->assertForbidden();

        // 5. Dashboard financiero: Ocultamiento estricto en backend
        $apiResponse = $this->getJson(route('api.dashboard'));
        $apiResponse->assertOk();
        $data = $apiResponse->json('metrics');
        $this->assertArrayNotHasKey('today_sales_total', $data);
        $this->assertArrayNotHasKey('month_sales_total', $data);
        $this->assertArrayNotHasKey('month_expenses_total', $data);
        $this->assertArrayNotHasKey('estimated_net_profit', $data);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. FINANCIAL INTEGRITY & PAYMENT TAMPERING (CWE-319 / CWE-835)
    |--------------------------------------------------------------------------
    */

    public function test_sale_validation_rejects_negative_service_fee_tax_inc_and_delivery_fee(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        $basePayload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'items' => [
                ['product_id' => $this->dishA->id, 'quantity' => 1],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 25000, 'cash_received' => 25000],
            ],
        ];

        // 1. service_fee negativo
        $res1 = $this->post(route('sales.store'), array_merge($basePayload, ['service_fee' => -1000]));
        $res1->assertSessionHasErrors('service_fee');

        // 2. tax_inc negativo
        $res2 = $this->post(route('sales.store'), array_merge($basePayload, ['tax_inc' => -500]));
        $res2->assertSessionHasErrors('tax_inc');

        // 3. delivery_fee negativo
        $res3 = $this->post(route('sales.store'), array_merge($basePayload, ['delivery_fee' => -2000]));
        $res3->assertSessionHasErrors('delivery_fee');
    }

    public function test_backend_strictly_enforces_authoritative_tax_inc_calculation(): void
    {
        $saleService = app(SaleService::class);

        // Subtotal = 25.000, Descuento = 0 -> Base Neta = 25.000
        // INC 8% exacto = 2.000. Si se intenta inyectar tax_inc = 5.000 arbitrario:
        $this->expectException(\App\Exceptions\Sales\InvalidSaleItemException::class);
        $this->expectExceptionMessage('El valor del Impuesto Nacional al Consumo (INC) debe ser del 8%');

        $saleService->processSale([
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'tax_inc' => 5000.00, // Arbitrario (debería ser 2000)
            'items' => [
                ['product_id' => $this->dishA->id, 'quantity' => 1],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 30000, 'cash_received' => 30000],
            ],
        ], $this->tenantA->id, $this->adminA->id);
    }

    public function test_payments_cents_mismatch_is_strictly_rejected(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        // Total venta: 25.000, Pago enviado: 24.999 (1 peso faltante)
        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'items' => [
                ['product_id' => $this->dishA->id, 'quantity' => 1],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 24999.00, 'cash_received' => 25000.00],
            ],
        ];

        $response = $this->post(route('sales.store'), $payload);
        $response->assertSessionHasErrors();
    }

    public function test_multiple_cash_payments_are_rejected(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'items' => [
                ['product_id' => $this->dishA->id, 'quantity' => 1],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 10000.00, 'cash_received' => 10000.00],
                ['method' => 'cash', 'amount' => 15000.00, 'cash_received' => 15000.00],
            ],
        ];

        $response = $this->post(route('sales.store'), $payload);
        $response->assertSessionHasErrors('payments');
    }

    public function test_authoritative_change_calculation_in_cash_payment(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'items' => [
                ['product_id' => $this->dishA->id, 'quantity' => 1],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 25000.00,
                    'cash_received' => 50000.00,
                    'change_given' => 99999.00, // Intento de inyectar cambio falso
                ],
            ],
        ];

        $response = $this->post(route('sales.store'), $payload);
        $response->assertRedirect();

        $sale = Sale::latest('id')->first();
        $this->assertNotNull($sale);
        $payment = $sale->payments()->first();
        $this->assertNotNull($payment);

        // El backend debe calcular estrictamente 50000 - 25000 = 25000
        $this->assertEquals(25000.00, (float) $payment->change_given);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. OWASP TOP 10: XSS & SANITIZATION (CWE-79 / CWE-1236)
    |--------------------------------------------------------------------------
    */

    public function test_thermal_ticket_templates_escape_html_and_xss_payloads(): void
    {
        $xssOrder = RestaurantOrder::create([
            'business_id' => $this->tenantA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->adminA->id,
            'order_number' => 'ORD-XSS-01',
            'order_type' => 'delivery',
            'status' => 'open',
            'customer_name' => '<script>alert("xss-customer")</script>',
            'delivery_address' => 'Calle 5 # 10-20 <img src=x onerror=alert("xss-addr")>',
            'delivery_notes' => '<svg/onload=alert("xss-notes")>',
            'delivery_phone' => '3001234567',
            'guest_count' => 2,
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->tenantA->id,
            'order_id' => $xssOrder->id,
            'product_id' => $this->dishA->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'notes' => '<b onmouseover=alert("xss-dish-note")>Término medio</b>',
            'status' => 'pending',
            'batch_number' => 1,
        ]);

        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        // 1. Comanda de cocina: ticket térmico 80 mm
        $kitchenRes = $this->get(route('restaurant.orders.kitchen-ticket', $xssOrder));
        $kitchenRes->assertOk();
        $kitchenRes->assertDontSee('<script>alert("xss-customer")</script>', false);
        $kitchenRes->assertSee('&lt;script&gt;alert(&quot;xss-customer&quot;)&lt;/script&gt;', false);
        $kitchenRes->assertDontSee('<b onmouseover=alert("xss-dish-note")>', false);

        // 2. Pre-cuenta informativa: ticket térmico 80 mm
        $preBillRes = $this->get(route('restaurant.orders.prebill', $xssOrder));
        $preBillRes->assertOk();
        $preBillRes->assertDontSee('<script>alert("xss-customer")</script>', false);

        // 3. Tirilla de despacho: ticket térmico 80 mm
        $dispatchRes = $this->get(route('restaurant.orders.dispatch-ticket', $xssOrder));
        $dispatchRes->assertOk();
        $dispatchRes->assertDontSee('<img src=x onerror=alert("xss-addr")>', false);
        $dispatchRes->assertDontSee('<svg/onload=alert("xss-notes")>', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. PRIVATE ATTACHMENTS & EXPENSES (CWE-200 / CWE-284)
    |--------------------------------------------------------------------------
    */

    public function test_expense_attachment_download_enforces_nosniff_and_blocks_cross_tenant(): void
    {
        Storage::fake('local');

        $supplier = Supplier::create([
            'business_id' => $this->tenantA->id,
            'name' => 'Carnes del Valle',
            'identification_number' => '900123456',
        ]);

        $file = UploadedFile::fake()->create('factura.pdf', 500, 'application/pdf');

        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->tenantA->id]);

        $createRes = $this->post(route('expenses.store'), [
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FAC-001',
            'issue_date' => now()->toDateString(),
            'amount' => 50000,
            'category' => 'supplies',
            'payment_method' => 'cash',
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
            'attachment' => $file,
        ]);
        $createRes->assertRedirect(route('expenses.index'));

        $expenseA = Expense::where('business_id', $this->tenantA->id)->first();
        $this->assertNotNull($expenseA);
        $this->assertNotNull($expenseA->attachment_path);

        // 1. Descarga legítima por Administrador del Tenant A: debe incluir header nosniff
        $downloadRes = $this->get(route('expenses.attachment', $expenseA));
        $downloadRes->assertOk();
        $downloadRes->assertHeader('X-Content-Type-Options', 'nosniff');

        // 2. Empleado del Tenant A recibe 403 Forbidden
        $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->tenantA->id]);
        $this->get(route('expenses.attachment', $expenseA))->assertForbidden();

        // 3. Administrador del Tenant B recibe 403 o 404
        $this->actingAs($this->adminB)
            ->withSession(['current_business_id' => $this->tenantB->id]);
        $crossRes = $this->get(route('expenses.attachment', $expenseA));
        $this->assertTrue(in_array($crossRes->status(), [403, 404], true));
    }
}
