<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Category;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ResetTenantDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:reset-data 
                            {email : El correo electrónico del usuario administrador del negocio} 
                            {--keep-menu : Conservar los productos y categorías existentes} 
                            {--force : Omitir confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purga de forma segura las transacciones, pedidos y datos de prueba de un negocio sin afectar usuarios ni la empresa';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'string', 'email', 'max:255']]
        );

        if ($validator->fails()) {
            $this->error('El correo electrónico proporcionado no es válido.');
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No se encontró ningún usuario con el correo '{$email}'.");
            return self::FAILURE;
        }

        if ($user->is_superadmin) {
            $this->error('No está permitido purgar datos de una cuenta de Superadministrador.');
            return self::FAILURE;
        }

        $business = $user->businesses()->first();

        if (! $business) {
            $this->error("El usuario '{$email}' no está vinculado a ningún negocio.");
            return self::FAILURE;
        }

        $businessId = $business->id;

        $this->info("==================================================");
        $this->info("LIMPIEZA DE DATOS DE PRUEBA");
        $this->info("==================================================");
        $this->line("Usuario:   {$user->name} ({$user->email})");
        $this->line("Negocio:   {$business->name} (ID: {$businessId}, Tipo: {$business->business_type})");
        $this->line("Opciones:  " . ($this->option('keep-menu') ? 'Conservar Carta/Menú' : 'Vaciar Carta/Menú (Productos y Categorías)'));
        $this->info("--------------------------------------------------");

        if (! $this->option('force')) {
            if (! $this->confirm("¿Estás seguro de que deseas purgar todas las órdenes, ventas y datos de prueba de '{$business->name}'?", false)) {
                $this->warn('Operación cancelada por el usuario.');
                return self::SUCCESS;
            }
        }

        try {
            DB::transaction(function () use ($businessId) {
                // 1. Líneas de comanda de restaurante
                $orderItemsCount = RestaurantOrderItem::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->delete();

                // 2. Comandas y pedidos de restaurante
                $ordersCount = RestaurantOrder::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->delete();

                // 3. Pagos de ventas
                $paymentsCount = SalePayment::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->delete();

                // 4. Detalles de ventas
                $saleIds = Sale::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->pluck('id');

                $detailsCount = SaleDetail::withoutGlobalScopes()
                    ->whereIn('sale_id', $saleIds)
                    ->delete();

                // 5. Cabeceras de ventas
                $salesCount = Sale::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->delete();

                // 6. Movimientos de inventario
                $movementsCount = InventoryMovement::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->delete();

                // 7. Gastos
                $expensesCount = Expense::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->delete();

                // 8. Restablecer todas las mesas a 'available'
                $tablesCount = RestaurantTable::withoutGlobalScopes()
                    ->where('business_id', $businessId)
                    ->update(['status' => 'available']);

                // 9. Productos y categorías (opcional)
                $productsCount = 0;
                $categoriesCount = 0;
                if (! $this->option('keep-menu')) {
                    $productsCount = Product::withoutGlobalScopes()
                        ->where('business_id', $businessId)
                        ->delete();

                    $categoriesCount = Category::withoutGlobalScopes()
                        ->where('business_id', $businessId)
                        ->delete();
                }

                $this->info("✓ Comandas eliminadas:            {$ordersCount} (con {$orderItemsCount} ítems)");
                $this->info("✓ Ventas eliminadas:              {$salesCount} (con {$detailsCount} detalles y {$paymentsCount} pagos)");
                $this->info("✓ Movimientos de stock borrados:  {$movementsCount}");
                $this->info("✓ Gastos eliminados:              {$expensesCount}");
                $this->info("✓ Mesas restablecidas a libres:   {$tablesCount}");

                if (! $this->option('keep-menu')) {
                    $this->info("✓ Productos/Platos eliminados:    {$productsCount}");
                    $this->info("✓ Categorías eliminadas:          {$categoriesCount}");
                }
            });

            $this->info("--------------------------------------------------");
            $this->info("¡Limpieza de datos de prueba completada exitosamente!");
            $this->line("El negocio está listo para comenzar pruebas limpias.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Ocurrió un error al purgar los datos: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
