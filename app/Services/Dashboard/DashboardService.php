<?php

namespace App\Services\Dashboard;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get operational and financial metrics strictly scoped to the specified tenant.
     * Uses America/Bogota timezone for accurate date boundaries.
     *
     * @param int $businessId
     * @param bool $isAdmin
     * @return array
     */
    public function getMetrics(int $businessId, bool $isAdmin = false): array
    {
        $nowBogota = Carbon::now('America/Bogota');
        $today = $nowBogota->copy()->startOfDay();
        $startOfMonth = $nowBogota->copy()->startOfMonth();
        $endOfMonth = $nowBogota->copy()->endOfMonth();

        // 1. Métricas de Ventas de Hoy (solo completadas)
        $todayMetrics = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereDate('sale_date', $today->toDateString())
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(total), 0) as aggregate_total')
            ->first();

        $todaySalesTotal = (float) ($todayMetrics->aggregate_total ?? 0);
        $todaySalesCount = (int) ($todayMetrics->aggregate_count ?? 0);

        // 2. Métricas de Ventas del Mes (solo completadas)
        $monthMetrics = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(total), 0) as aggregate_total')
            ->first();

        $monthSalesTotal = (float) ($monthMetrics->aggregate_total ?? 0);
        $monthSalesCount = (int) ($monthMetrics->aggregate_count ?? 0);

        // 3. Métricas de Catálogo y Clientes
        $totalProducts = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->count();

        $totalCustomers = Customer::where('business_id', $businessId)
            ->where('is_active', true)
            ->count();

        // 4. Resolución de tipo de negocio
        $business = \App\Models\Business::find($businessId);
        $isRestaurant = $business ? $business->isRestaurant() : false;

        // 5. Alertas de Stock Crítico (stock <= min_stock) - Exclusivo Comercio (Retail)
        if ($isRestaurant) {
            $lowStockCount = 0;
            $outOfStockCount = 0;
            $lowStockProducts = collect();
        } else {
            $lowStockQuery = Product::where('business_id', $businessId)
                ->where('is_active', true)
                ->whereColumn('stock', '<=', 'min_stock');

            $lowStockCount = (int) (clone $lowStockQuery)->count();
            $outOfStockCount = (int) Product::where('business_id', $businessId)
                ->where('is_active', true)
                ->where('stock', '<=', 0)
                ->count();

            $lowStockProducts = (clone $lowStockQuery)
                ->with('category')
                ->orderBy('stock', 'asc')
                ->limit(5)
                ->get();
        }

        // 6. Ventas Recientes
        $recentSales = Sale::where('business_id', $businessId)
            ->with(['customer', 'user'])
            ->latest('sale_date')
            ->latest('id')
            ->limit(5)
            ->get();

        // 7. Top 5 Productos Más Vendidos
        $topProducts = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.stock',
                DB::raw('SUM(sale_details.quantity) as total_sold_quantity'),
                DB::raw('SUM(sale_details.subtotal) as total_sold_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.stock')
            ->orderByDesc('total_sold_quantity')
            ->limit(5)
            ->get();

        // 8. Métricas Operativas de Restaurante (Bloque R-D)
        $restaurantMetrics = [];
        if ($isRestaurant) {
            $totalTables = \App\Models\RestaurantTable::where('business_id', $businessId)->count();
            $occupiedTables = \App\Models\RestaurantTable::where('business_id', $businessId)
                ->whereIn('status', ['occupied', 'billed'])
                ->count();

            $activeDeliveries = \App\Models\RestaurantOrder::where('business_id', $businessId)
                ->whereIn('order_type', ['delivery', 'takeout'])
                ->whereIn('status', ['open', 'in_kitchen', 'dispatched'])
                ->count();

            $restaurantMetrics = [
                'is_restaurant' => true,
                'total_tables' => $totalTables,
                'occupied_tables' => $occupiedTables,
                'active_deliveries' => $activeDeliveries,
            ];
        } else {
            $restaurantMetrics = [
                'is_restaurant' => false,
                'total_tables' => 0,
                'occupied_tables' => 0,
                'active_deliveries' => 0,
            ];
        }

        $baseMetrics = array_merge([
            'today_sales_count' => $todaySalesCount,
            'month_sales_count' => $monthSalesCount,
            'total_products' => $totalProducts,
            'total_customers' => $totalCustomers,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'low_stock_products' => $lowStockProducts,
            'recent_sales' => $recentSales,
            'top_products' => $topProducts,
        ], $restaurantMetrics);

        // Métricas Financieras: Exclusivas para Administradores
        if ($isAdmin) {
            $monthExpenses = Expense::where('business_id', $businessId)
                ->whereBetween('issue_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->get();

            $monthExpensesTotal = (float) $monthExpenses->sum('amount');
            $monthExpensesPending = (float) $monthExpenses->where('status', 'pending')->sum('amount');
            $estimatedNetProfit = round($monthSalesTotal - $monthExpensesTotal, 2);

            return array_merge($baseMetrics, [
                'today_sales_total' => $todaySalesTotal,
                'month_sales_total' => $monthSalesTotal,
                'month_expenses_total' => $monthExpensesTotal,
                'month_expenses_pending' => $monthExpensesPending,
                'estimated_net_profit' => $estimatedNetProfit,
            ]);
        }

        return $baseMetrics;
    }
}
