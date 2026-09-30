<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminA;
    protected User $employeeA;
    protected User $adminB;

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

        $this->businessA = Business::create([
            'name' => 'Restaurante Valle A',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->businessB = Business::create([
            'name' => 'Restaurante Valle B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create();
        $this->businessA->users()->attach($this->adminA->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->employeeA = User::factory()->create();
        $this->businessA->users()->attach($this->employeeA->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->adminB = User::factory()->create();
        $this->businessB->users()->attach($this->adminB->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_raw_material_and_dish_products(): void
    {
        // 1. Insumo con stock decimal
        $responseRaw = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('products.store'), [
                'name' => 'Carne Molida Especial',
                'sku' => 'INS-CARNE-01',
                'product_type' => 'raw_material',
                'base_unit' => 'gram',
                'cost_price' => 20,
                'sale_price' => 0,
                'stock' => '2500.500',
                'min_stock' => '500.000',
            ]);

        $responseRaw->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'business_id' => $this->businessA->id,
            'sku' => 'INS-CARNE-01',
            'product_type' => 'raw_material',
            'base_unit' => 'gram',
            'stock' => 2500.5,
        ]);

        // 2. Plato preparado
        $responseDish = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('products.store'), [
                'name' => 'Plátano Maduro con Carne',
                'sku' => 'PLATO-001',
                'product_type' => 'dish',
                'base_unit' => 'unit',
                'cost_price' => 8000,
                'sale_price' => 18000,
                'stock' => 0,
                'min_stock' => 0,
            ]);

        $responseDish->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'business_id' => $this->businessA->id,
            'sku' => 'PLATO-001',
            'product_type' => 'dish',
            'sale_price' => 18000,
        ]);
    }

    public function test_admin_can_create_recipe_for_dish(): void
    {
        $dish = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Plátano con Carne',
            'sku' => 'PLT-001',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 5000,
            'sale_price' => 15000,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $carne = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Carne de Res',
            'sku' => 'INS-001',
            'product_type' => 'raw_material',
            'base_unit' => 'gram',
            'cost_price' => 25,
            'sale_price' => 0,
            'stock' => 5000,
            'min_stock' => 500,
        ]);

        $platano = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Plátano Maduro',
            'sku' => 'INS-002',
            'product_type' => 'raw_material',
            'base_unit' => 'unit',
            'cost_price' => 1000,
            'sale_price' => 0,
            'stock' => 50,
            'min_stock' => 10,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('recipes.store'), [
                'product_id' => $dish->id,
                'name' => 'Receta Estándar Plátano con Carne',
                'is_active' => '1',
                'items' => [
                    [
                        'ingredient_id' => $carne->id,
                        'quantity_per_portion' => '150.000',
                        'unit' => 'gram',
                    ],
                    [
                        'ingredient_id' => $platano->id,
                        'quantity_per_portion' => '1.000',
                        'unit' => 'unit',
                    ],
                ],
            ]);

        $response->assertRedirect(route('recipes.index'));
        $this->assertDatabaseHas('recipes', [
            'business_id' => $this->businessA->id,
            'product_id' => $dish->id,
            'name' => 'Receta Estándar Plátano con Carne',
        ]);

        $recipe = Recipe::withoutGlobalScopes()->where('product_id', $dish->id)->first();
        $this->assertNotNull($recipe);
        $this->assertCount(2, $recipe->items);

        $this->assertDatabaseHas('recipe_items', [
            'recipe_id' => $recipe->id,
            'ingredient_id' => $carne->id,
            'quantity_per_portion' => 150.0,
            'unit' => 'gram',
        ]);
    }

    public function test_dish_can_only_have_one_recipe_per_business(): void
    {
        $dish = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Bandeja Paisa',
            'sku' => 'PLT-002',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 10000,
            'sale_price' => 25000,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $carne = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Carne Molida',
            'sku' => 'INS-003',
            'product_type' => 'raw_material',
            'base_unit' => 'gram',
            'cost_price' => 20,
            'sale_price' => 0,
            'stock' => 2000,
            'min_stock' => 200,
        ]);

        // Primera receta
        Recipe::create([
            'business_id' => $this->businessA->id,
            'product_id' => $dish->id,
            'name' => 'Receta Original',
        ]);

        // Intentar registrar segunda receta para el mismo plato
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('recipes.store'), [
                'product_id' => $dish->id,
                'name' => 'Receta Duplicada',
                'items' => [
                    [
                        'ingredient_id' => $carne->id,
                        'quantity_per_portion' => '200',
                        'unit' => 'gram',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['product_id']);
    }

    public function test_recipe_rejects_duplicate_ingredients(): void
    {
        $dish = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Sancocho de Gallina',
            'sku' => 'PLT-003',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 8000,
            'sale_price' => 20000,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $gallina = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Gallina Criolla',
            'sku' => 'INS-004',
            'product_type' => 'raw_material',
            'base_unit' => 'gram',
            'cost_price' => 15,
            'sale_price' => 0,
            'stock' => 10000,
            'min_stock' => 1000,
        ]);

        // Enviar dos veces el mismo insumo en la receta
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('recipes.store'), [
                'product_id' => $dish->id,
                'name' => 'Receta Sancocho',
                'items' => [
                    [
                        'ingredient_id' => $gallina->id,
                        'quantity_per_portion' => '200',
                        'unit' => 'gram',
                    ],
                    [
                        'ingredient_id' => $gallina->id,
                        'quantity_per_portion' => '100',
                        'unit' => 'gram',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_recipe_and_ingredients_are_isolated_by_tenant(): void
    {
        // Insumo perteneciente a Business B
        $insumoB = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Ingrediente Exclusivo B',
            'sku' => 'INS-B-01',
            'product_type' => 'raw_material',
            'base_unit' => 'gram',
            'cost_price' => 10,
            'sale_price' => 0,
            'stock' => 500,
            'min_stock' => 50,
        ]);

        $dishA = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Plato A',
            'sku' => 'PLT-A-01',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 5000,
            'sale_price' => 12000,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        // Business A intenta usar insumo de Business B
        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('recipes.store'), [
                'product_id' => $dishA->id,
                'name' => 'Receta Cross Tenant',
                'items' => [
                    [
                        'ingredient_id' => $insumoB->id,
                        'quantity_per_portion' => '100',
                        'unit' => 'gram',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['items.0.ingredient_id']);

        // Receta creada en Business B no puede ser vista ni editada por Business A
        $recipeB = Recipe::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'product_id' => $insumoB->id,
            'name' => 'Receta Privada B',
        ]);

        $viewResponse = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('recipes.show', $recipeB));

        $viewResponse->assertStatus(404);
    }

    public function test_employee_cannot_create_or_update_recipe(): void
    {
        $dish = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Empanada Valluna',
            'sku' => 'PLT-EMP-01',
            'product_type' => 'dish',
            'base_unit' => 'unit',
            'cost_price' => 1500,
            'sale_price' => 3000,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $carne = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Carne Mechada',
            'sku' => 'INS-EMP-01',
            'product_type' => 'raw_material',
            'base_unit' => 'gram',
            'cost_price' => 20,
            'sale_price' => 0,
            'stock' => 1000,
            'min_stock' => 100,
        ]);

        $response = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('recipes.store'), [
                'product_id' => $dish->id,
                'name' => 'Receta Empleado No Autorizada',
                'items' => [
                    [
                        'ingredient_id' => $carne->id,
                        'quantity_per_portion' => '50',
                        'unit' => 'gram',
                    ],
                ],
            ]);

        $response->assertStatus(403);
    }
}
