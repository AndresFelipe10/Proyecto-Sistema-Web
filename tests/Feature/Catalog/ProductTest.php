<?php

namespace Tests\Feature\Catalog;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUser;
    protected User $employeeUser;

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

        $this->businessA = Business::create(['name' => 'Empresa A']);
        $this->businessB = Business::create(['name' => 'Empresa B']);

        $this->adminUser = User::factory()->create();
        $this->businessA->users()->attach($this->adminUser->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUser = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUser->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);
    }

    public function test_admin_and_employee_can_view_products_list_and_details(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Zapatillas Nike Cali',
            'sku' => 'NK-001',
            'cost_price' => 50000,
            'sale_price' => 95000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        // Admin checks
        $adminResponse = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('products.index'));

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Zapatillas Nike Cali');
        $adminResponse->assertSee('NK-001');

        $detailResponse = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('products.show', $product));

        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('Zapatillas Nike Cali');

        // Employee checks
        $empResponse = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('products.index'));

        $empResponse->assertStatus(200);
        $empResponse->assertSee('Zapatillas Nike Cali');
    }

    public function test_admin_can_create_product(): void
    {
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Calzado',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('products.store'), [
                'name' => 'Mocasines Cuero',
                'sku' => 'MOC-100',
                'category_id' => $category->id,
                'cost_price' => 45000,
                'sale_price' => 89000,
                'stock' => 15,
                'min_stock' => 3,
                'description' => 'Cuero genuino hecho en Cali',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'business_id' => $this->businessA->id,
            'sku' => 'MOC-100',
            'name' => 'Mocasines Cuero',
            'stock' => 15,
        ]);
    }

    public function test_employee_cannot_create_product(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('products.store'), [
                'name' => 'Producto No Autorizado',
                'sku' => 'NA-001',
                'cost_price' => 10000,
                'sale_price' => 20000,
                'stock' => 5,
                'min_stock' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_product(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto Original',
            'sku' => 'PROD-001',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 5,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('products.update', $product), [
                'name' => 'Producto Modificado',
                'sku' => 'PROD-001',
                'cost_price' => 12000,
                'sale_price' => 25000,
                'stock' => 8,
                'min_stock' => 2,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Producto Modificado',
            'cost_price' => 12000,
            'sale_price' => 25000,
        ]);
    }

    public function test_employee_cannot_update_product(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Original',
            'sku' => 'PROD-EMP',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 5,
            'min_stock' => 1,
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('products.update', $product), [
                'name' => 'Hack Attempt',
                'sku' => 'PROD-EMP',
                'cost_price' => 10000,
                'sale_price' => 20000,
                'stock' => 5,
                'min_stock' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_product(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Para Eliminar',
            'sku' => 'DEL-001',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_sku_must_be_unique_within_same_business(): void
    {
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Primer Producto',
            'sku' => 'SKU-DUP',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 5,
            'min_stock' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('products.store'), [
                'name' => 'Segundo Producto Mismo SKU',
                'sku' => 'SKU-DUP',
                'cost_price' => 15000,
                'sale_price' => 30000,
                'stock' => 10,
                'min_stock' => 2,
            ]);

        $response->assertSessionHasErrors(['sku']);
    }

    public function test_same_sku_allowed_in_different_businesses(): void
    {
        // Business B already has SKU 'SKU-COMMON'
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Producto Empresa B',
            'sku' => 'SKU-COMMON',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 5,
            'min_stock' => 1,
        ]);

        // Business A registers the same SKU 'SKU-COMMON'
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('products.store'), [
                'name' => 'Producto Empresa A Mismo SKU',
                'sku' => 'SKU-COMMON',
                'cost_price' => 12000,
                'sale_price' => 24000,
                'stock' => 8,
                'min_stock' => 2,
            ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'business_id' => $this->businessA->id,
            'sku' => 'SKU-COMMON',
        ]);
        $this->assertDatabaseHas('products', [
            'business_id' => $this->businessB->id,
            'sku' => 'SKU-COMMON',
        ]);
    }

    public function test_products_are_isolated_between_businesses(): void
    {
        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Producto Exclusivo Empresa A',
            'sku' => 'EXCL-A',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 5,
            'min_stock' => 1,
        ]);

        Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Producto Exclusivo Empresa B',
            'sku' => 'EXCL-B',
            'cost_price' => 10000,
            'sale_price' => 20000,
            'stock' => 5,
            'min_stock' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('products.index'));

        $response->assertSee('Producto Exclusivo Empresa A');
        $response->assertDontSee('Producto Exclusivo Empresa B');
    }
}
