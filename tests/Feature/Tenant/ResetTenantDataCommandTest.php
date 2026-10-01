<?php

namespace Tests\Feature\Tenant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetTenantDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Business $tenantRestaurant;
    protected Business $otherRestaurant;
    protected User $tenantUser;
    protected User $otherUser;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->tenantRestaurant = Business::create([
            'name' => 'Restaurante Prueba',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->otherRestaurant = Business::create([
            'name' => 'Restaurante Otro',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->tenantUser = User::factory()->create([
            'email' => 'pruebar@gmail.com',
            'is_superadmin' => false,
        ]);
        $this->tenantRestaurant->users()->attach($this->tenantUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'otro@gmail.com',
            'is_superadmin' => false,
        ]);
        $this->otherRestaurant->users()->attach($this->otherUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'email' => 'super@gmail.com',
            'is_superadmin' => true,
        ]);
    }

    public function test_reset_data_command_fails_if_user_not_found(): void
    {
        $this->artisan('tenant:reset-data noexiste@gmail.com --force')
            ->expectsOutputToContain('No se encontró ningún usuario con el correo')
            ->assertExitCode(1);
    }

    public function test_reset_data_command_fails_if_user_is_superadmin(): void
    {
        $this->artisan('tenant:reset-data super@gmail.com --force')
            ->expectsOutputToContain('No está permitido purgar datos de una cuenta de Superadministrador')
            ->assertExitCode(1);
    }

    public function test_reset_data_command_purges_tenant_data_and_resets_tables(): void
    {
        // Setup data for tenantRestaurant
        $table1 = RestaurantTable::create([
            'business_id' => $this->tenantRestaurant->id,
            'name' => 'Mesa 1',
            'status' => 'occupied',
        ]);
        $table2 = RestaurantTable::create([
            'business_id' => $this->tenantRestaurant->id,
            'name' => 'Mesa 2',
            'status' => 'billed',
        ]);

        $cat1 = Category::create([
            'business_id' => $this->tenantRestaurant->id,
            'name' => 'Almuerzos',
            'is_active' => true,
        ]);

        $prod1 = Product::create([
            'business_id' => $this->tenantRestaurant->id,
            'category_id' => $cat1->id,
            'name' => 'Bandeja Paisa',
            'sku' => 'PLATO-001',
            'sale_price' => 25000,
            'cost_price' => 12000,
            'stock' => 0,
            'is_active' => true,
        ]);

        $order1 = RestaurantOrder::create([
            'business_id' => $this->tenantRestaurant->id,
            'user_id' => $this->tenantUser->id,
            'table_id' => $table1->id,
            'order_type' => 'table',
            'order_number' => 'CMD-001',
            'status' => 'in_kitchen',
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->tenantRestaurant->id,
            'order_id' => $order1->id,
            'product_id' => $prod1->id,
            'unit_price' => 25000,
            'quantity' => 1,
            'subtotal' => 25000,
            'batch_number' => 1,
        ]);

        $sale1 = Sale::create([
            'business_id' => $this->tenantRestaurant->id,
            'user_id' => $this->tenantUser->id,
            'restaurant_order_id' => $order1->id,
            'invoice_number' => 'FAC-001',
            'sale_date' => now(),
            'subtotal' => 25000,
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SaleDetail::create([
            'sale_id' => $sale1->id,
            'product_id' => $prod1->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
        ]);

        SalePayment::create([
            'business_id' => $this->tenantRestaurant->id,
            'sale_id' => $sale1->id,
            'method' => 'cash',
            'amount' => 25000,
        ]);

        // Setup data for otherRestaurant (must not be touched)
        $otherTable = RestaurantTable::create([
            'business_id' => $this->otherRestaurant->id,
            'name' => 'Mesa Otra',
            'status' => 'occupied',
        ]);
        $otherCat = Category::create([
            'business_id' => $this->otherRestaurant->id,
            'name' => 'Otra Cat',
            'is_active' => true,
        ]);
        $otherProd = Product::create([
            'business_id' => $this->otherRestaurant->id,
            'category_id' => $otherCat->id,
            'name' => 'Otro Plato',
            'sku' => 'OTRO-001',
            'sale_price' => 10000,
            'cost_price' => 5000,
            'stock' => 10,
            'is_active' => true,
        ]);
        $otherOrder = RestaurantOrder::create([
            'business_id' => $this->otherRestaurant->id,
            'user_id' => $this->otherUser->id,
            'table_id' => $otherTable->id,
            'order_type' => 'table',
            'order_number' => 'CMD-OTRO',
            'status' => 'open',
            'subtotal' => 10000,
            'total' => 10000,
        ]);

        // Execute reset command
        $this->artisan('tenant:reset-data pruebar@gmail.com --force')
            ->expectsOutputToContain('Limpieza de datos de prueba completada exitosamente')
            ->assertExitCode(0);

        // Assert tenant data was purged
        $this->assertDatabaseMissing('restaurant_orders', ['id' => $order1->id]);
        $this->assertDatabaseMissing('restaurant_order_items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('sales', ['id' => $sale1->id]);
        $this->assertDatabaseMissing('products', ['id' => $prod1->id]);
        $this->assertDatabaseMissing('categories', ['id' => $cat1->id]);

        // Assert tables reset to 'available'
        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table1->id,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table2->id,
            'status' => 'available',
        ]);

        // Assert User and Business still exist
        $this->assertDatabaseHas('users', ['email' => 'pruebar@gmail.com']);
        $this->assertDatabaseHas('businesses', ['id' => $this->tenantRestaurant->id]);
        $this->assertDatabaseHas('business_user', [
            'user_id' => $this->tenantUser->id,
            'business_id' => $this->tenantRestaurant->id,
        ]);

        // Assert other tenant data remains 100% intact
        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $otherTable->id,
            'status' => 'occupied',
        ]);
        $this->assertDatabaseHas('products', ['id' => $otherProd->id]);
        $this->assertDatabaseHas('categories', ['id' => $otherCat->id]);
        $this->assertDatabaseHas('restaurant_orders', ['id' => $otherOrder->id]);
    }
}
