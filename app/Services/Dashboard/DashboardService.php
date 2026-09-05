<?php

namespace App\Services\Dashboard;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get operational and financial metrics strictly scoped to the specified tenant.
     *
     * @param int $businessId
     * @return array
     */
    public function getMetrics(int $businessId): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // 1. Métricas de Ventas de Hoy (solo completadas)
        $todaySalesQuery = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereDate('sale_date', $today);

        $todaySalesTotal = (float) (clone $todaySalesQuery)->sum('total');
        $todaySalesCount = (int) (clone $todaySalesQuery)->count();

        // 2. Métricas de Ventas del Mes (solo completadas)
        $monthSalesQuery = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$startOfMonth, $endOfMonth]);

        $monthSalesTotal = (float) (clone $monthSalesQuery)->sum('total');
        $monthSalesCount = (int) (clone $monthSalesQuery)->count();

        // 3. Métricas de Catálogo y Clientes
        $totalProducts = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->count();

        $totalCustomers = Customer::where('business_id', $businessId)
            ->where('is_active', true)
            ->count();

        // 4. Alertas de Stock Crítico (stock <= min_stock)
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

        // 5. Ventas Recientes
        $recentSales = Sale::where('business_id', $businessId)
            ->with(['customer', 'user'])
            ->latest('sale_date')
            ->latest('id')
            ->limit(5)
            ->get();

        // 6. Top 5 Productos Más Vendidos
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

        return [
            'today_sales_total' => $todaySalesTotal,
            'today_sales_count' => $todaySalesCount,
            'month_sales_total' => $monthSalesTotal,
            'month_sales_count' => $monthSalesCount,
            'total_products' => $totalProducts,
            'total_customers' => $totalCustomers,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'low_stock_products' => $lowStockProducts,
            'recent_sales' => $recentSales,
            'top_products' => $topProducts,
        ];
    }
}
