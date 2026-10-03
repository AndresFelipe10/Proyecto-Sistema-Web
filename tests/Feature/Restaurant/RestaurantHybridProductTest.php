<?php

namespace Tests\Feature\Restaurant;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\Sales\InvalidSaleItemException;
use App\Models\Business;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantHybridProductTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $restaurant;
    protected User $admin;
    protected Category $categoryMenu;
    protected Category $categorySupplements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->restaurant = Business::create([
            'name' => 'Restaurante Fit & Healthy Cali',
            'business_type' => 'restaurant',
        ]);

        $this->admin = User::factory()->create(['name' => 'Chef Propietario']);
        $this->restaurant->users()->attach($this->admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->categoryMenu = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Platos Principales',
        ]);

        $this->categorySupplements = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Suplementos y Proteínas',
        ]);
    }

    public function test_restaurant_can_create_merchandise_standard_product_with_cost_and_stock(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('products.store'), [
            'name' => 'Proteína Whey Isolate 2lb',
            'sku' => 'SUP-WHEY-01',
            'product_type' => 'standard',
            'category_id' => $this->categorySupplements->id,
            'cost_price' => 65000.00,
            'sale_price' => 110000.00,
            'stock' => 12,
            'min_stock' => 3,
            'description' => 'Tarro de proteína isolada sabor vainilla',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::withoutGlobalScopes()
            ->where('business_id', $this->restaurant->id)
            ->where('sku', 'SUP-WHEY-01')
            ->first();

        $this->assertNotNull($product);
        $this->assertTrue($product->isStandard());
        $this->assertFalse($product->isDish());
        $this->assertEquals(65000.00, $product->cost_price);
        $this->assertEquals(110000.00, $product->sale_price);
        $this->assertEquals(12, $product->stock);

        // Validar pestañas en catálogo del restaurante
        // Pestaña Mercancía: debe mostrar el producto
        $responseMerch = $this->get(route('products.index', ['tab' => 'merchandise']));
        $responseMerch->assertStatus(200);
        $responseMerch->assertSee('Proteína Whey Isolate 2lb');
        $responseMerch->assertSee('Mercancía');

        // Pestaña Menú: no debe mostrar mercancía
        $responseMenu = $this->get(route('products.index', ['tab' => 'menu']));
        $responseMenu->assertStatus(200);
        $responseMenu->assertDontSee('Proteína Whey Isolate 2lb');
    }

    public function test_selling_merchandise_in_restaurant_atomically_decrements_stock_under_lock(): void
    {
        $merchandise = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurant->id,
            'name' => 'Creatina Monohidrato 300g',
            'sku' => 'SUP-CREA-01',
            'product_type' => 'standard',
            'base_unit' => 'unit',
            'cost_price' => 45000.00,
            'sale_price' => 75000.00,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $saleService = app(SaleService::class);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'order_type' => 'takeout',
            'items' => [
                [
                    'product_id' => $merchandise->id,
                    'quantity' => 3,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 225000.00,
                    'cash_received' => 225000.00,
                    'change_given' => 0.00,
                ],
            ],
        ];

        $sale = $saleService->processSale($payload, $this->restaurant->id, $this->admin->id);

        $this->assertNotNull($sale);
        $this->assertEquals(225000.00, $sale->total);

        // Descuento atómico de stock: 10 - 3 = 7
        $merchandise->refresh();
        $this->assertEquals(7, (int) $merchandise->stock);

        // Trazabilidad de inventario
        $this->assertDatabaseHas('inventory_movements', [
            'business_id' => $this->restaurant->id,
            'product_id' => $merchandise->id,
            'sale_id' => $sale->id,
            'type' => InventoryMovement::TYPE_EXIT,
            'quantity' => 3,
            'previous_stock' => 10,
            'new_stock' => 7,
        ]);
    }

    public function test_selling_merchandise_with_insufficient_stock_throws_insufficient_stock_exception(): void
    {
        $merchandise = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurant->id,
            'name' => 'Barra de Proteína Chocolate',
            'sku' => 'SNK-BAR-01',
            'product_type' => 'standard',
            'base_unit' => 'unit',
            'cost_price' => 5000.00,
            'sale_price' => 9000.00,
            'stock' => 2,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        $saleService = app(SaleService::class);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'order_type' => 'takeout',
            'items' => [
                [
                    'product_id' => $merchandise->id,
                    'quantity' => 5, // Solicita 5 pero solo hay 2
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 45000.00,
                ],
            ],
        ];

        $this->expectException(InsufficientStockException::class);

        $saleService->processSale($payload, $this->restaurant->id, $this->admin->id);

        // El stock permanece intacto
        $merchandise->refresh();
        $this->assertEquals(2, (int) $merchandise->stock);
    }

    public function test_merchandise_sale_is_excluded_from_inc_8_and_calculates_commercial_profit(): void
    {
        $dish = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurant->id,
            'name' => 'Bowl Fit de Salmón y Quinoa',
            'sku' => 'PLT-BOWL-01',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 0.00,
            'sale_price' => 30000.00,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $merchandise = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurant->id,
            'name' => 'Tarro Multivitamínico 60 caps',
            'sku' => 'SUP-VIT-01',
            'product_type' => 'standard',
            'base_unit' => 'unit',
            'cost_price' => 35000.00,
            'sale_price' => 55000.00,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $saleService = app(SaleService::class);

        // Venta híbrida: 1 Bowl ($30.000) + 1 Multivitamínico ($55.000) = Subtotal $85.000
        // INC 8% aplica ÚNICAMENTE sobre comidas preparadas: $30.000 * 0.08 = $2.400
        // Total esperado = $85.000 + $2.400 = $87.400
        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'order_type' => 'table',
            'tax_inc' => 2400.00, // Correcto: 8% solo del plato
            'items' => [
                [
                    'product_id' => $dish->id,
                    'quantity' => 1,
                ],
                [
                    'product_id' => $merchandise->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 87400.00,
                    'cash_received' => 90000.00,
                    'change_given' => 2600.00,
                ],
            ],
        ];

        $sale = $saleService->processSale($payload, $this->restaurant->id, $this->admin->id);

        $this->assertNotNull($sale);
        $this->assertEquals(87400.00, $sale->total);
        $this->assertEquals(2400.00, $sale->tax_inc);

        // Verificar que si se intenta cobrar INC sobre la mercancía ($85.000 * 0.08 = $6.800) se rechaza
        $invalidPayload = $payload;
        $invalidPayload['tax_inc'] = 6800.00;
        $invalidPayload['payments'][0]['amount'] = 91800.00;
        $invalidPayload['payments'][0]['cash_received'] = 92000.00;
        $invalidPayload['payments'][0]['change_given'] = 200.00;

        $this->expectException(InvalidSaleItemException::class);
        $saleService->processSale($invalidPayload, $this->restaurant->id, $this->admin->id);
    }

    public function test_cancelling_sale_in_restaurant_restores_merchandise_stock_but_not_dishes(): void
    {
        $dish = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurant->id,
            'name' => 'Smoothie Proteico de Frutos Rojos',
            'sku' => 'PLT-SMOOTH-01',
            'product_type' => 'dish',
            'sale_price' => 15000.00,
            'stock' => 0,
            'is_active' => true,
        ]);

        $merchandise = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurant->id,
            'name' => 'Shaker Mezclador 700ml',
            'sku' => 'ACC-SHAKER-01',
            'product_type' => 'standard',
            'cost_price' => 12000.00,
            'sale_price' => 25000.00,
            'stock' => 8,
            'is_active' => true,
        ]);

        $saleService = app(SaleService::class);

        $payload = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'order_type' => 'takeout',
            'items' => [
                ['product_id' => $dish->id, 'quantity' => 2],
                ['product_id' => $merchandise->id, 'quantity' => 2],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 80000.00],
            ],
        ];

        $sale = $saleService->processSale($payload, $this->restaurant->id, $this->admin->id);

        $merchandise->refresh();
        $this->assertEquals(6, (int) $merchandise->stock); // 8 - 2 = 6

        // Anular la venta
        $cancelledSale = $saleService->cancelSale($sale, $this->admin->id);

        $this->assertEquals('cancelled', $cancelledSale->status);

        // Stock de mercancía debe ser restaurado a 8
        $merchandise->refresh();
        $this->assertEquals(8, (int) $merchandise->stock);

        // Dish permanece sin stock
        $dish->refresh();
        $this->assertEquals(0, (int) $dish->stock);
    }
}
