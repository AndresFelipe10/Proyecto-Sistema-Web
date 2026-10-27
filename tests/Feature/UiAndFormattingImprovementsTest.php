<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiAndFormattingImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $retailBusiness;
    protected Business $restaurantBusiness;
    protected User $retailAdmin;
    protected User $restaurantAdmin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->retailBusiness = Business::create([
            'name' => 'Comercio Retail Cali',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantBusiness = Business::create([
            'name' => 'Restaurante Gourmet Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailAdmin = User::factory()->create([
            'name' => 'Andrés Admin Retail',
            'email' => 'admin_retail@puntostock.co',
        ]);

        $this->retailBusiness->users()->attach($this->retailAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->restaurantAdmin = User::factory()->create([
            'name' => 'Mariana Admin Restaurant',
            'email' => 'admin_restaurant@puntostock.co',
        ]);

        $this->restaurantBusiness->users()->attach($this->restaurantAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'General',
        ]);
    }

    /**
     * Test 1: Precios con formato colombiano y precisión matemática.
     * Enviar creación y actualización de un producto con purchase_price = "25.000" y sale_price = "50.000"
     * (tanto formateado con punto como numérico puro 25000 y 50000).
     * Certificar que se persiste en base de datos exactamente como 25000.00 y 50000.00 (y nunca como 25.00 o 50.00).
     */
    public function test_product_pricing_supports_colombian_dot_format_and_pure_numbers(): void
    {
        // 1. Creación con string formateado con puntos de miles colombianos
        $responseCreate = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->post(route('products.store'), [
                'name' => 'Chaqueta de Cuero',
                'sku' => 'CHAQ-001',
                'category_id' => $this->category->id,
                'purchase_price' => '25.000',
                'sale_price' => '50.000',
                'stock' => 10,
                'min_stock' => 2,
                'product_type' => 'standard',
                'is_active' => '1',
            ]);

        $responseCreate->assertRedirect(route('products.index'));

        $product = Product::withoutGlobalScopes()
            ->where('business_id', $this->retailBusiness->id)
            ->where('sku', 'CHAQ-001')
            ->firstOrFail();

        // Certificar que se persiste exactamente como 25000.00 y 50000.00 y nunca 25.00 o 50.00
        $this->assertEquals(25000.00, (float) $product->cost_price);
        $this->assertEquals(50000.00, (float) $product->sale_price);
        $this->assertEquals(25000.00, (float) $product->purchase_price);
        $this->assertNotEquals(25.00, (float) $product->cost_price);
        $this->assertNotEquals(50.00, (float) $product->sale_price);

        // 2. Actualización con valores numéricos puros (25000 y 50000)
        $responseUpdatePure = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->put(route('products.update', $product), [
                'name' => 'Chaqueta de Cuero Editada',
                'sku' => 'CHAQ-001',
                'category_id' => $this->category->id,
                'purchase_price' => 25000,
                'sale_price' => 50000,
                'stock' => 12,
                'min_stock' => 2,
                'product_type' => 'standard',
                'is_active' => '1',
            ]);

        $responseUpdatePure->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertEquals(25000.00, (float) $product->cost_price);
        $this->assertEquals(50000.00, (float) $product->sale_price);

        // 3. Actualización nuevamente con formato colombiano con puntos ("75.500" y "120.000")
        $responseUpdateDot = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->put(route('products.update', $product), [
                'name' => 'Chaqueta Premium',
                'sku' => 'CHAQ-001',
                'category_id' => $this->category->id,
                'purchase_price' => '75.500',
                'sale_price' => '120.000',
                'stock' => 15,
                'min_stock' => 3,
                'product_type' => 'standard',
                'is_active' => '1',
            ]);

        $responseUpdateDot->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertEquals(75500.00, (float) $product->cost_price);
        $this->assertEquals(120000.00, (float) $product->sale_price);
    }

    /**
     * Test 2: Etiquetas separadoras <optgroup> en Ventas de Mostrador.
     * Crear un producto de Carta / Menú de Cocina y uno de Mercancía de Mostrador.
     * Hacer GET /sales/create y verificar que se rendericen ambos <optgroup>
     * (Mercancía de Mostrador y Carta / Menú de Cocina) y que ambos productos sean seleccionables.
     */
    public function test_pos_counter_sales_renders_optgroups_for_modalities(): void
    {
        // Producto de Mercancía de Mostrador
        $merchandise = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBusiness->id,
            'name' => 'Gaseosa Postobón 350ml',
            'sku' => 'BEV-001',
            'product_type' => 'standard',
            'cost_price' => 2000,
            'sale_price' => 4500,
            'stock' => 24,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        // Producto de Carta / Menú de Cocina
        $dish = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBusiness->id,
            'name' => 'Lomo Salteado Valluno',
            'sku' => 'DISH-001',
            'product_type' => 'dish',
            'cost_price' => 12000,
            'sale_price' => 28000,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('sales.create'));

        $response->assertStatus(200);

        // Verificar la presencia de ambos optgroups nativos
        $response->assertSee('label="📦 Mercancía de Mostrador"', false);
        $response->assertSee('label="🍽️ Carta / Menú de Cocina"', false);

        // Verificar que ambos productos están presentes dentro del selector
        $response->assertSee('value="' . $merchandise->id . '"', false);
        $response->assertSee('Gaseosa Postobón 350ml');
        $response->assertSee('value="' . $dish->id . '"', false);
        $response->assertSee('Lomo Salteado Valluno');
    }

    /**
     * Test 3: Estilos de impresión en Cuadre de Caja.
     * Hacer GET /cash-register y verificar la presencia de las reglas @media print
     * que desactivan overflow en .table-responsive, establecen position: static !important para evitar superposición de tarjetas,
     * configuran word-break: normal !important en celdas, display: table-row-group !important en tfoot,
     * y grillas compactas de impresión, erradicando word-break: break-word sobre .table td.
     */
    public function test_cash_register_print_styles_prevent_overlap_and_table_scrollbar(): void
    {
        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('reports.cash-register'));

        $response->assertStatus(200);

        // Verificar reglas estrictas de impresión en la respuesta renderizada
        $response->assertSee('@media print', false);
        $response->assertSee('.table-responsive', false);
        $response->assertSee('overflow: visible !important;', false);
        $response->assertSee('position: static !important;', false);
        $response->assertSee('break-inside: avoid;', false);
        $response->assertSee('word-break: normal !important;', false);
        $response->assertSee('display: table-row-group !important;', false);
        $response->assertSee('.print-kpi-grid', false);
        $response->assertSee('grid-template-columns: repeat(4, 1fr) !important;', false);
        $response->assertSee('.print-nowrap', false);

        // Verificar que no contenga word-break: break-word ni reglas destructivas de 100% width
        $response->assertDontSee('word-break: break-word !important;', false);
        $response->assertDontSee('word-break: break-all !important;', false);
        $response->assertDontSee('.row > [class*="col-"] { width: 100% !important;', false);

        // Verificar además directamente el archivo de vista cash-register/index.blade.php
        $cashRegisterViewPath = resource_path('views/cash-register/index.blade.php');
        $this->assertFileExists($cashRegisterViewPath);
        $viewContent = file_get_contents($cashRegisterViewPath);
        $this->assertStringContainsString('@media print', $viewContent);
        $this->assertStringContainsString('word-break: normal !important;', $viewContent);
        $this->assertStringContainsString('display: table-row-group !important;', $viewContent);
        $this->assertStringContainsString('grid-template-columns: repeat(4, 1fr) !important;', $viewContent);
        $this->assertStringNotContainsString('word-break: break-word !important;', $viewContent);
        $this->assertStringNotContainsString('word-break: break-all !important;', $viewContent);
        $this->assertStringNotContainsString('.row > [class*="col-"] { width: 100% !important;', $viewContent);
    }

    /**
     * Test 4: Paginación en español con espaciado.
     * Generar más de 15 registros en Ventas y verificar en el HTML renderizado que aparece "Mostrando"
     * y "resultados", y que assertDontSee('Showing') y assertDontSee('results') pasen exitosamente.
     */
    public function test_pagination_renders_spanish_labels_with_spacing(): void
    {
        // Generar 16 ventas para forzar la paginación de 15 items por página
        for ($i = 1; $i <= 16; $i++) {
            Sale::withoutGlobalScopes()->create([
                'business_id' => $this->retailBusiness->id,
                'user_id' => $this->retailAdmin->id,
                'invoice_number' => sprintf('VTA-PAG-%04d', $i),
                'sale_date' => now()->subMinutes(17 - $i),
                'subtotal' => 10000.00,
                'tax' => 0.00,
                'total' => 10000.00,
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('sales.index'));

        $response->assertStatus(200);

        // Verificar traducción al español y estructura de Bootstrap 5
        $response->assertSee('Mostrando');
        $response->assertSee('resultados');
        $response->assertSee('gap-3 flex-wrap py-2', false);

        // Verificar que no aparezca el texto en inglés
        $response->assertDontSee('Showing');
        $response->assertDontSee('results');
    }

    /**
     * Test 5: Botón de ojito en Login.
     * Hacer GET /login y verificar la presencia de #togglePasswordBtn y bi-eye.
     */
    public function test_login_page_renders_toggle_password_eye_button(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('id="togglePasswordBtn"', false);
        $response->assertSee('id="togglePasswordIcon"', false);
        $response->assertSee('bi-eye', false);
    }

    /**
     * Test Adicional: Normalización de moneda COP en gastos.
     */
    public function test_expense_amount_supports_colombian_dot_format(): void
    {
        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->post(route('expenses.store'), [
                'description' => 'Servicio de Internet Empresa',
                'amount' => '120.000',
                'issue_date' => now()->format('Y-m-d'),
                'status' => 'paid',
                'paid_at' => now()->format('Y-m-d'),
                'payment_method' => 'cash',
                'category' => 'utilities',
            ]);

        $response->assertRedirect(route('expenses.index'));

        $expense = Expense::withoutGlobalScopes()
            ->where('business_id', $this->retailBusiness->id)
            ->where('description', 'Servicio de Internet Empresa')
            ->firstOrFail();

        $this->assertEquals(120000.00, (float) $expense->amount);
    }
}
