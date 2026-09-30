<?php

namespace Tests\Feature\Restaurant;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\Sales\InsufficientIngredientStockException;
use App\Models\Business;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeInventoryDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $restaurantBiz;
    protected User $adminUser;
    protected SaleService $saleService;

    protected Product $dish;
    protected Product $carne;
    protected Product $platano;
    protected Product $arroz;
    protected Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saleService = app(SaleService::class);

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->restaurantBiz = Business::create([
            'name' => 'Restaurante Cali Tradicional',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->restaurantBiz->users()->attach($this->adminUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Crear insumos
        $this->carne = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'name' => 'Carne de Res',
            'sku' => 'INS-CARNE',
            'product_type' => 'raw_material',
            'base_unit' => 'gram',
            'cost_price' => 30,
            'sale_price' => 0,
            'stock' => 2000.000,
            'min_stock' => 300.000,
        ]);

        $this->platano = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'name' => 'Plátano Verde',
            'sku' => 'INS-PLATANO',
            'product_type' => 'raw_material',
            'base_unit' => 'unit',
            'cost_price' => 800,
            'sale_price' => 0,
            'stock' => 20.000,
            'min_stock' => 5.000,
        ]);

        $this->arroz = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'name' => 'Arroz Blanco',
            'sku' => 'INS-ARROZ',
            'product_type' => 'raw_material',
            'base_unit' => 'gram',
            'cost_price' => 5,
            'sale_price' => 0,
            'stock' => 2000.000,
            'min_stock' => 200.000,
        ]);

        // Crear plato
        $this->dish = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'name' => 'Plátano con Carne y Arroz',
            'sku' => 'PLATO-ESPECIAL',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 7000,
            'sale_price' => 22000,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        // Crear receta: 150g carne, 1 plátano, 180g arroz
        $this->recipe = Recipe::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'product_id' => $this->dish->id,
            'name' => 'Fórmula Estándar Plátano Especial',
            'is_active' => true,
        ]);

        RecipeItem::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'recipe_id' => $this->recipe->id,
            'ingredient_id' => $this->carne->id,
            'quantity_per_portion' => 150.000,
            'unit' => 'gram',
        ]);

        RecipeItem::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'recipe_id' => $this->recipe->id,
            'ingredient_id' => $this->platano->id,
            'quantity_per_portion' => 1.000,
            'unit' => 'unit',
        ]);

        RecipeItem::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'recipe_id' => $this->recipe->id,
            'ingredient_id' => $this->arroz->id,
            'quantity_per_portion' => 180.000,
            'unit' => 'gram',
        ]);
    }

    public function test_selling_one_dish_deducts_exact_recipe_portions_from_ingredients(): void
    {
        $saleData = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $this->dish->id,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'cash',
        ];

        $sale = $this->saleService->processSale(
            data: $saleData,
            businessId: $this->restaurantBiz->id,
            userId: $this->adminUser->id
        );

        $this->assertNotNull($sale);
        $this->assertEquals(22000, $sale->total);

        // Comprobar deducción exacta de insumos
        $this->carne->refresh();
        $this->platano->refresh();
        $this->arroz->refresh();

        $this->assertEquals(1850.0, (float) $this->carne->stock);
        $this->assertEquals(19.0, (float) $this->platano->stock);
        $this->assertEquals(1820.0, (float) $this->arroz->stock);

        // Movimientos de inventario generados con motivo 'receta_venta'
        $movements = InventoryMovement::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            ->where('type', InventoryMovement::TYPE_EXIT)
            ->get();

        $this->assertCount(3, $movements);
        foreach ($movements as $mov) {
            $this->assertEquals('receta_venta', $mov->reason);
        }
    }

    public function test_selling_multiple_dishes_multiplies_portion_consumption(): void
    {
        $saleData = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $this->dish->id,
                    'quantity' => 3, // 3 platos
                ],
            ],
            'payment_method' => 'cash',
        ];

        $this->saleService->processSale(
            data: $saleData,
            businessId: $this->restaurantBiz->id,
            userId: $this->adminUser->id
        );

        $this->carne->refresh();
        $this->platano->refresh();
        $this->arroz->refresh();

        // 2000 - (150 * 3 = 450) = 1550
        $this->assertEquals(1550.0, (float) $this->carne->stock);
        // 20 - (1 * 3 = 3) = 17
        $this->assertEquals(17.0, (float) $this->platano->stock);
        // 2000 - (180 * 3 = 540) = 1460
        $this->assertEquals(1460.0, (float) $this->arroz->stock);
    }

    public function test_sale_aborted_completely_if_any_single_ingredient_has_insufficient_stock(): void
    {
        // Dejar carne con solo 100g (insuficiente para 1 plato que requiere 150g)
        $this->carne->update(['stock' => 100.000]);

        $saleData = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $this->dish->id,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'cash',
        ];

        $exceptionCaught = false;
        try {
            $this->saleService->processSale(
                data: $saleData,
                businessId: $this->restaurantBiz->id,
                userId: $this->adminUser->id
            );
        } catch (InsufficientIngredientStockException $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('Carne de Res', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught);

        // Cero stock negativo y cero deducción parcial en los demás insumos
        $this->carne->refresh();
        $this->platano->refresh();
        $this->arroz->refresh();

        $this->assertEquals(100.0, (float) $this->carne->stock);
        $this->assertEquals(20.0, (float) $this->platano->stock);
        $this->assertEquals(2000.0, (float) $this->arroz->stock);

        // Sin registros de venta
        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_details');
        $this->assertDatabaseEmpty('inventory_movements');
    }

    public function test_concurrent_sales_competing_for_last_portion_prevent_negative_stock(): void
    {
        // Carne suficiente para exactamente 1 plato (150g)
        $this->carne->update(['stock' => 150.000]);

        $saleData = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $this->dish->id,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'cash',
        ];

        // Primera venta se procesa con éxito
        $sale1 = $this->saleService->processSale(
            data: $saleData,
            businessId: $this->restaurantBiz->id,
            userId: $this->adminUser->id
        );
        $this->assertNotNull($sale1);

        $this->carne->refresh();
        $this->assertEquals(0.0, (float) $this->carne->stock);

        // Segunda venta concurrente intenta vender 1 plato más: debe fallar sin stock negativo
        $this->expectException(InsufficientStockException::class);

        $this->saleService->processSale(
            data: $saleData,
            businessId: $this->restaurantBiz->id,
            userId: $this->adminUser->id
        );

        $this->carne->refresh();
        $this->assertEquals(0.0, (float) $this->carne->stock);
    }

    public function test_cancelling_dish_sale_restores_all_recipe_ingredients(): void
    {
        $saleData = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $this->dish->id,
                    'quantity' => 2, // Descuenta 300g carne, 2 plátanos, 360g arroz
                ],
            ],
            'payment_method' => 'cash',
        ];

        $sale = $this->saleService->processSale(
            data: $saleData,
            businessId: $this->restaurantBiz->id,
            userId: $this->adminUser->id
        );

        $this->carne->refresh();
        $this->assertEquals(1700.0, (float) $this->carne->stock);

        // Anular la venta
        $cancelledSale = $this->saleService->cancelSale(
            sale: $sale,
            userId: $this->adminUser->id
        );

        $this->assertEquals('cancelled', $cancelledSale->status);

        // Todos los insumos restaurados al 100% de su saldo original
        $this->carne->refresh();
        $this->platano->refresh();
        $this->arroz->refresh();

        $this->assertEquals(2000.0, (float) $this->carne->stock);
        $this->assertEquals(20.0, (float) $this->platano->stock);
        $this->assertEquals(2000.0, (float) $this->arroz->stock);

        // Movimientos de restitución con motivo 'receta_anulacion'
        $restorations = InventoryMovement::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            ->where('type', InventoryMovement::TYPE_ENTRY)
            ->get();

        $this->assertCount(3, $restorations);
        foreach ($restorations as $rest) {
            $this->assertEquals('receta_anulacion', $rest->reason);
        }
    }

    public function test_traditional_retail_sales_continue_functioning_without_touching_recipes(): void
    {
        $retailProduct = Product::withoutGlobalScopes()->create([
            'business_id' => $this->restaurantBiz->id,
            'name' => 'Gaseosa Postobón 350ml',
            'sku' => 'GAS-350',
            'product_type' => 'standard',
            'base_unit' => 'unit',
            'cost_price' => 1500,
            'sale_price' => 3500,
            'stock' => 24,
            'min_stock' => 6,
        ]);

        $saleData = [
            'sale_date' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $retailProduct->id,
                    'quantity' => 4,
                ],
            ],
            'payment_method' => 'cash',
        ];

        $sale = $this->saleService->processSale(
            data: $saleData,
            businessId: $this->restaurantBiz->id,
            userId: $this->adminUser->id
        );

        $retailProduct->refresh();
        $this->assertEquals(20.0, (float) $retailProduct->stock);

        $movement = InventoryMovement::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(4.0, (float) $movement->quantity);
        $this->assertEquals("Venta #{$sale->invoice_number}", $movement->reason);
    }
}
