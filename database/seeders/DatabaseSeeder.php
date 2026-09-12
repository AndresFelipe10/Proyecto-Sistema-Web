<?php

namespace Database\Seeders;

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
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demonstration data.
     */
    public function run(): void
    {
        // 1. Roles del sistema
        $adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            [
                'name' => 'Administrador',
                'description' => 'Acceso y administración total del emprendimiento',
            ]
        );

        $employeeRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            [
                'name' => 'Empleado',
                'description' => 'Consulta de inventario, registro de ventas y clientes',
            ]
        );

        // 2. Usuarios de demostración
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Administrador Demo',
                'password' => Hash::make('password123'),
            ]
        );

        $employeeUser = User::firstOrCreate(
            ['email' => 'empleado@demo.com'],
            [
                'name' => 'Vendedor Demo',
                'password' => Hash::make('password123'),
            ]
        );

        // 3. Emprendimiento principal
        $business = Business::firstOrCreate(
            ['name' => 'Emprendimiento Demo Cali'],
            [
                'nit' => '900888777-5',
                'address' => 'Calle 5 # 38-25, Cali, Valle del Cauca',
                'phone' => '+57 315 555 0199',
                'email' => 'contacto@emprendimientocali.com',
                'is_active' => true,
            ]
        );

        // Vincular usuarios al emprendimiento si no están vinculados
        if (!$business->users()->where('users.id', $adminUser->id)->exists()) {
            $business->users()->attach($adminUser->id, [
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]);
        }

        if (!$business->users()->where('users.id', $employeeUser->id)->exists()) {
            $business->users()->attach($employeeUser->id, [
                'role_id' => $employeeRole->id,
                'is_active' => true,
            ]);
        }

        // 4. Categorías
        $catCalzado = Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Calzado Deportivo'],
            [
                'description' => 'Tenis y calzado urbano y deportivo',
                'is_active' => true,
            ]
        );

        $catTextil = Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Textil y Confección'],
            [
                'description' => 'Camisetas, pantalones y prendas de vestir',
                'is_active' => true,
            ]
        );

        $catAccesorios = Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Accesorios de Moda'],
            [
                'description' => 'Bolsos, gorras y complementos',
                'is_active' => true,
            ]
        );

        // 5. Clientes
        $cliente1 = Customer::firstOrCreate(
            ['business_id' => $business->id, 'identification_number' => '1144001001'],
            [
                'name' => 'Carlos Mario Restrepo',
                'email' => 'carlos.restrepo@ejemplo.com',
                'phone' => '3104567890',
                'address' => 'Barrio San Fernando, Cali',
                'is_active' => true,
            ]
        );

        $cliente2 = Customer::firstOrCreate(
            ['business_id' => $business->id, 'identification_number' => '1144002002'],
            [
                'name' => 'María Fernanda Ospina',
                'email' => 'maria.ospina@ejemplo.com',
                'phone' => '3157891234',
                'address' => 'Barrio Granada, Cali',
                'is_active' => true,
            ]
        );

        // 6. Proveedores
        $prov1 = Supplier::firstOrCreate(
            ['business_id' => $business->id, 'identification_number' => '900123456-1'],
            [
                'name' => 'Textiles del Valle S.A.S.',
                'contact_name' => 'Roberto Gómez',
                'email' => 'ventas@textilesdelvalle.com',
                'phone' => '6025551234',
                'address' => 'Zona Industrial Acopi, Yumbo',
                'is_active' => true,
            ]
        );

        $prov2 = Supplier::firstOrCreate(
            ['business_id' => $business->id, 'identification_number' => '900987654-3'],
            [
                'name' => 'Calzado Industrial de Occidente',
                'contact_name' => 'Patricia Caicedo',
                'email' => 'pedidos@calzadooccidente.com',
                'phone' => '6026669876',
                'address' => 'Barrio Obrero, Cali',
                'is_active' => true,
            ]
        );

        // 7. Productos con diferentes estados de stock (Normal, Bajo Stock, Agotado)
        $prod1 = Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => 'CAL-001'],
            [
                'category_id' => $catCalzado->id,
                'name' => 'Tenis Running Pro Valle',
                'description' => 'Calzado deportivo ergonómico suela antideslizante',
                'cost_price' => 110000.00,
                'sale_price' => 180000.00,
                'stock' => 22,
                'min_stock' => 5,
                'is_active' => true,
            ]
        );

        $prod2 = Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => 'ROP-001'],
            [
                'category_id' => $catTextil->id,
                'name' => 'Camiseta 100% Algodón Cali',
                'description' => 'Camiseta casual con estampado representativo',
                'cost_price' => 24000.00,
                'sale_price' => 48000.00,
                'stock' => 3, // Stock bajo (3 <= 10)
                'min_stock' => 10,
                'is_active' => true,
            ]
        );

        $prod3 = Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => 'ACC-001'],
            [
                'category_id' => $catAccesorios->id,
                'name' => 'Bolso Artesanal Cuero Café',
                'description' => 'Bolso mediano cosido a mano con cuero del Valle',
                'cost_price' => 55000.00,
                'sale_price' => 95000.00,
                'stock' => 0, // Agotado
                'min_stock' => 4,
                'is_active' => true,
            ]
        );

        $prod4 = Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => 'ACC-002'],
            [
                'category_id' => $catAccesorios->id,
                'name' => 'Gorra Urbana Bordada',
                'description' => 'Gorra ajustable bordado especial',
                'cost_price' => 18000.00,
                'sale_price' => 38000.00,
                'stock' => 15,
                'min_stock' => 5,
                'is_active' => true,
            ]
        );

        // 8. Movimientos de inventario iniciales (Entradas)
        $initialMovements = [
            [$prod1, 25, 'Inventario inicial'],
            [$prod2, 5, 'Inventario inicial'],
            [$prod4, 15, 'Inventario inicial'],
        ];

        foreach ($initialMovements as [$product, $qty, $reason]) {
            InventoryMovement::firstOrCreate(
                [
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'type' => 'entry',
                    'quantity' => $qty,
                ],
                [
                    'user_id' => $adminUser->id,
                    'previous_stock' => 0,
                    'new_stock' => $qty,
                    'reason' => $reason,
                    'movement_date' => Carbon::now()->subDays(5),
                ]
            );
        }

        // 9. Venta de demostración
        $sale = Sale::firstOrCreate(
            ['business_id' => $business->id, 'invoice_number' => 'FAC-000001'],
            [
                'user_id' => $employeeUser->id,
                'customer_id' => $cliente1->id,
                'sale_date' => Carbon::now()->subDays(1),
                'subtotal' => 180000.00 + (48000.00 * 2), // 276000
                'discount' => 6000.00,
                'total' => 270000.00,
                'payment_method' => 'cash',
                'status' => 'completed',
                'notes' => 'Venta presencial de mostrador con descuento promocional.',
            ]
        );

        SaleDetail::firstOrCreate(
            ['sale_id' => $sale->id, 'product_id' => $prod1->id],
            [
                'quantity' => 1,
                'unit_price' => 180000.00,
                'subtotal' => 180000.00,
            ]
        );

        SaleDetail::firstOrCreate(
            ['sale_id' => $sale->id, 'product_id' => $prod2->id],
            [
                'quantity' => 2,
                'unit_price' => 48000.00,
                'subtotal' => 96000.00,
            ]
        );
    }
}
