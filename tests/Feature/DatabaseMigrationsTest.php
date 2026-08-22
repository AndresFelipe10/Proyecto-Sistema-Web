<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_eleven_core_tables_exist(): void
    {
        $expectedTables = [
            'users',
            'businesses',
            'roles',
            'business_user',
            'categories',
            'products',
            'customers',
            'suppliers',
            'sales',
            'sale_details',
            'inventory_movements',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table '{$table}' does not exist.");
        }
    }

    public function test_tables_have_tenant_and_integrity_columns(): void
    {
        // categories
        $this->assertTrue(Schema::hasColumns('categories', ['id', 'business_id', 'name', 'description', 'is_active']));

        // products
        $this->assertTrue(Schema::hasColumns('products', ['id', 'business_id', 'category_id', 'name', 'sku', 'cost_price', 'sale_price', 'stock', 'min_stock', 'is_active']));

        // customers
        $this->assertTrue(Schema::hasColumns('customers', ['id', 'business_id', 'name', 'identification_number', 'phone', 'email', 'address', 'is_active']));

        // suppliers
        $this->assertTrue(Schema::hasColumns('suppliers', ['id', 'business_id', 'name', 'identification_number', 'contact_name', 'phone', 'email', 'address', 'is_active']));

        // sales
        $this->assertTrue(Schema::hasColumns('sales', ['id', 'business_id', 'user_id', 'customer_id', 'invoice_number', 'sale_date', 'subtotal', 'discount', 'total', 'payment_method', 'status']));

        // sale_details
        $this->assertTrue(Schema::hasColumns('sale_details', ['id', 'sale_id', 'product_id', 'quantity', 'unit_price', 'subtotal']));

        // inventory_movements
        $this->assertTrue(Schema::hasColumns('inventory_movements', ['id', 'business_id', 'product_id', 'user_id', 'sale_id', 'type', 'quantity', 'previous_stock', 'new_stock', 'reason', 'movement_date']));
    }

    public function test_models_relationships_and_foreign_keys(): void
    {
        $user = User::factory()->create();
        $business = Business::create([
            'name' => 'Emprendimiento Cali Test',
            'nit' => '900123456-1',
            'phone' => '3001234567',
            'email' => 'contacto@calitest.com',
            'address' => 'Av 6N # 20-30',
        ]);

        $adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
            'description' => 'Acceso total al negocio',
        ]);

        // business_user pivot
        $business->users()->attach($user->id, ['role_id' => $adminRole->id, 'is_active' => true]);

        $this->assertCount(1, $business->users);
        $this->assertEquals($user->id, $business->users->first()->id);
        $this->assertCount(1, $user->businesses);

        // Category & Product
        $category = Category::create([
            'business_id' => $business->id,
            'name' => 'Ropa y Calzado',
        ]);

        $product = Product::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name' => 'Camiseta Estampada',
            'sku' => 'CAM-001',
            'cost_price' => 15000.00,
            'sale_price' => 30000.00,
            'stock' => 50,
            'min_stock' => 10,
        ]);

        $this->assertEquals($business->id, $product->business->id);
        $this->assertEquals($category->id, $product->category->id);

        // Customer & Supplier
        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'Maria Perez',
            'identification_number' => '1144123456',
            'phone' => '3157890123',
        ]);

        $supplier = Supplier::create([
            'business_id' => $business->id,
            'name' => 'Textiles del Valle S.A.S',
            'identification_number' => '890300123-4',
        ]);

        $this->assertEquals($business->id, $customer->business->id);
        $this->assertEquals($business->id, $supplier->business->id);

        // Sale & SaleDetail
        $sale = Sale::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'FAC-0001',
            'sale_date' => now(),
            'subtotal' => 60000.00,
            'discount' => 0.00,
            'total' => 60000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 30000.00,
            'subtotal' => 60000.00,
        ]);

        $this->assertCount(1, $sale->details);
        $this->assertEquals($product->id, $sale->details->first()->product->id);

        // InventoryMovement
        $movement = InventoryMovement::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'sale_id' => $sale->id,
            'type' => InventoryMovement::TYPE_EXIT,
            'quantity' => 2,
            'previous_stock' => 50,
            'new_stock' => 48,
            'reason' => 'Venta #FAC-0001',
            'movement_date' => now(),
        ]);

        $this->assertEquals($product->id, $movement->product->id);
        $this->assertEquals($sale->id, $movement->sale->id);
    }
}
