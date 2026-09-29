<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockBSecurityTest extends TestCase
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
    }

    public function test_default_customer_saves_222222222222_and_consumidor_final_snapshot(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('sales.store'), [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'customer_id' => null,
                'discount_percentage' => 0,
                'payment_method' => 'cash',
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $sale = Sale::latest()->first();

        $this->assertNull($sale->customer_id);
        $this->assertEquals('CONSUMIDOR FINAL', $sale->customer_name);
        $this->assertEquals('222222222222', $sale->customer_document);
    }

    public function test_customer_search_returns_only_matching_tenant_customers(): void
    {
        // Cliente en negocio A
        Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Andres Felipe Ospina',
            'document' => '1144001999',
            'phone' => '3151112233',
            'is_active' => true,
        ]);

        // Cliente en negocio B con documento y nombre similares
        Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Andres Felipe Cruz',
            'document' => '1144001888',
            'phone' => '3169998877',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->getJson(route('customers.search', ['q' => '1144001']));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data);
        $this->assertEquals('Andres Felipe Ospina', $data[0]['name']);
        $this->assertEquals('1144001999', $data[0]['document']);

        // Usuario B buscando no debe ver el cliente de negocio A
        $responseB = $this->actingAs($this->adminB)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->getJson(route('customers.search', ['q' => '1144001']));

        $responseB->assertStatus(200);
        $dataB = $responseB->json();

        $this->assertCount(1, $dataB);
        $this->assertEquals('Andres Felipe Cruz', $dataB[0]['name']);
        $this->assertEquals('1144001888', $dataB[0]['document']);
    }

    public function test_customer_search_requires_minimum_3_characters(): void
    {
        Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Diana Marcela',
            'document' => '94500123',
            'is_active' => true,
        ]);

        // 2 caracteres devuelve arreglo vacío
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->getJson(route('customers.search', ['q' => '94']));

        $response->assertStatus(200);
        $this->assertEmpty($response->json());

        // 3 caracteres devuelve el resultado
        $response3 = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->getJson(route('customers.search', ['q' => '945']));

        $response3->assertStatus(200);
        $this->assertCount(1, $response3->json());
    }

    public function test_customer_search_escapes_like_wildcards(): void
    {
        Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Normal',
            'document' => '11223344',
            'is_active' => true,
        ]);

        Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Cliente 100% Especial',
            'document' => '99887766',
            'is_active' => true,
        ]);

        // Buscar con '%' no debe actuar como comodín de todo
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->getJson(route('customers.search', ['q' => '100%']));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('Cliente 100% Especial', $data[0]['name']);
    }

    public function test_customer_document_validation_rejects_invalid_format_and_dian_generic_document(): void
    {
        // Documento reservado para Consumidor Final de la DIAN debe ser rechazado
        $responseDian = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Falso Consumidor Final',
                'document' => '222222222222',
            ]);

        $responseDian->assertSessionHasErrors(['document']);

        // Formato con menos de 5 dígitos debe ser rechazado
        $responseShort = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Documento Corto',
                'document' => '1234',
            ]);

        $responseShort->assertSessionHasErrors(['document']);

        // Formato con caracteres no numéricos o inválidos
        $responseAlpha = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Letras en Documento',
                'document' => 'CC12345678',
            ]);

        $responseAlpha->assertSessionHasErrors(['document']);

        // Formato válido de NIT con guión y dígito de verificación
        $responseNit = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Empresa Cali SAS',
                'document' => '900123456-1',
            ]);

        $responseNit->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'business_id' => $this->businessA->id,
            'name' => 'Empresa Cali SAS',
            'document' => '900123456-1',
        ]);
    }

    public function test_customer_document_must_be_unique_per_business_but_allowed_across_different_businesses(): void
    {
        // Crear cliente en negocio A
        $resA1 = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Cliente A1',
                'document' => '1144005566',
            ]);
        $resA1->assertSessionHasNoErrors();

        // Intento de duplicar en el mismo negocio A debe fallar
        $resA2 = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Cliente A2',
                'document' => '1144005566',
            ]);
        $resA2->assertSessionHasErrors(['document']);

        // Mismo documento en un negocio DISTINTO (B) debe ser permitido
        $resB = $this->actingAs($this->adminB)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->post(route('customers.store'), [
                'name' => 'Cliente B1',
                'document' => '1144005566',
            ]);
        $resB->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'business_id' => $this->businessB->id,
            'document' => '1144005566',
        ]);
    }

    public function test_buyer_snapshot_in_sales_is_immutable_when_customer_is_subsequently_edited(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Nombre Original Cliente',
            'document' => '1144007788',
            'phone' => '3157778899',
            'is_active' => true,
        ]);

        $saleService = app(SaleService::class);
        $sale = $saleService->processSale(
            data: [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'customer_id' => $customer->id,
                'discount_percentage' => 0,
                'payment_method' => 'cash',
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'quantity' => 1,
                    ],
                ],
            ],
            businessId: $this->businessA->id,
            userId: $this->adminA->id,
        );

        $this->assertEquals('Nombre Original Cliente', $sale->customer_name);
        $this->assertEquals('1144007788', $sale->customer_document);

        // Actualizar el cliente con nuevo nombre y documento
        $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('customers.update', $customer), [
                'name' => 'Nombre Modificado Posterior',
                'document' => '1144009900',
                'phone' => '3150000000',
                'is_active' => '1',
            ]);

        // La venta previa debe mantener exactamente la copia original del comprador
        $saleFresh = $sale->fresh();
        $this->assertEquals('Nombre Original Cliente', $saleFresh->customer_name);
        $this->assertEquals('1144007788', $saleFresh->customer_document);

        // Vistas de detalle e impresión deben leer el snapshot (limpiar flash session previa)
        $this->flushSession();

        $showResponse = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.show', $sale));

        $showResponse->assertSee('Nombre Original Cliente');
        $showResponse->assertSee('1144007788');
        $showResponse->assertDontSee('Nombre Modificado Posterior');

        $invoiceResponse = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.invoice', $sale));

        $invoiceResponse->assertSee('Nombre Original Cliente');
        $invoiceResponse->assertSee('1144007788');

        $receiptResponse = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.receipt', $sale));

        $receiptResponse->assertSee('Nombre Original Cliente');
        $receiptResponse->assertSee('1144007788');
    }

    public function test_customer_name_with_html_is_properly_escaped_in_views(): void
    {
        $maliciousName = '<script>alert("xss")</script><b>Empresa Segura</b>';

        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => $maliciousName,
            'document' => '1144003322',
            'is_active' => true,
        ]);

        $saleService = app(SaleService::class);
        $sale = $saleService->processSale(
            data: [
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'customer_id' => $customer->id,
                'discount_percentage' => 0,
                'payment_method' => 'card',
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'quantity' => 1,
                    ],
                ],
            ],
            businessId: $this->businessA->id,
            userId: $this->adminA->id,
        );

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('sales.print.invoice', $sale));

        $response->assertStatus(200);
        // Debe escapar el script, no ejecutar HTML crudo
        $response->assertDontSee('<script>alert("xss")</script>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }

    public function test_quick_customer_creation_via_api_returns_json_and_handles_validation_errors(): void
    {
        // Solicitud AJAX/JSON exitosa desde el modal
        $responseOk = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson(route('customers.store'), [
                'name' => 'Nuevo Cliente Rápido',
                'document' => '1144004455',
                'phone' => '3182223344',
                'email' => 'rapido@test.com',
                'is_active' => true,
            ]);

        $responseOk->assertStatus(201);
        $responseOk->assertJsonFragment([
            'name' => 'Nuevo Cliente Rápido',
            'document' => '1144004455',
            'phone' => '3182223344',
        ]);

        // Solicitud AJAX/JSON con error de validación
        $responseErr = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson(route('customers.store'), [
                'name' => 'Cliente Faltante Documento',
                'document' => '',
            ]);

        $responseErr->assertStatus(422);
        $responseErr->assertJsonValidationErrors(['document']);
    }

    public function test_customer_name_and_phone_validation_rejects_invalid_inputs_and_normalizes_empty_strings(): void
    {
        // 1. Nombre compuesto únicamente por números debe ser rechazado
        $resNumName = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson(route('customers.store'), [
                'name' => '123456',
                'document' => '1144007788',
            ]);

        $resNumName->assertStatus(422);
        $resNumName->assertJsonValidationErrors(['name']);

        // 2. Teléfono con texto alfabético debe ser rechazado
        $resAlphaPhone = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson(route('customers.store'), [
                'name' => 'Cliente Valido',
                'document' => '1144007789',
                'phone' => 'ssss',
            ]);

        $resAlphaPhone->assertStatus(422);
        $resAlphaPhone->assertJsonValidationErrors(['phone']);

        // 3. Documento con letras debe ser rechazado
        $resAlphaDoc = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson(route('customers.store'), [
                'name' => 'Cliente Valido',
                'document' => 'pepito',
            ]);

        $resAlphaDoc->assertStatus(422);
        $resAlphaDoc->assertJsonValidationErrors(['document']);

        // 4. Cliente válido con teléfono válido y nombre con números y letras
        $resOk = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->postJson(route('customers.store'), [
                'name' => 'Bodega Central 1A',
                'document' => '1144007790',
                'phone' => '+57 315 123-4567',
                'email' => '   ', // debe normalizarse a null
            ]);

        $resOk->assertStatus(201);
        $customer = Customer::where('document', '1144007790')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('Bodega Central 1A', $customer->name);
        $this->assertEquals('+57 315 123-4567', $customer->phone);
        $this->assertNull($customer->email);
    }
}
