<?php

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Generate sales report for a business within an optional date range and filters.
     *
     * @param int $businessId
     * @param array $filters
     * @return array
     */
    public function getSalesReport(int $businessId, array $filters = []): array
    {
        $dateFrom = !empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = !empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : Carbon::now()->endOfDay();

        $query = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->with(['customer', 'user']);

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $sales = $query->orderByDesc('sale_date')->get();

        $totalRevenue = (float) $sales->sum('total');
        $salesCount = $sales->count();
        $averageTicket = $salesCount > 0 ? round($totalRevenue / $salesCount, 2) : 0.0;
        $totalDiscounts = (float) $sales->sum('discount');

        $byPaymentMethod = $sales->groupBy('payment_method')->map(function ($group, $method) {
            return [
                'method' => $method,
                'count' => $group->count(),
                'total' => (float) $group->sum('total'),
            ];
        });

        $customers = Customer::where('business_id', $businessId)->orderBy('name')->get();

        return [
            'sales' => $sales,
            'customers' => $customers,
            'total_revenue' => $totalRevenue,
            'sales_count' => $salesCount,
            'average_ticket' => $averageTicket,
            'total_discounts' => $totalDiscounts,
            'by_payment_method' => $byPaymentMethod,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'selected_payment_method' => $filters['payment_method'] ?? '',
            'selected_customer_id' => $filters['customer_id'] ?? '',
        ];
    }

    /**
     * Generate inventory valuation report for a business.
     *
     * @param int $businessId
     * @param array $filters
     * @return array
     */
    public function getInventoryValuationReport(int $businessId, array $filters = []): array
    {
        $query = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->with('category');

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->get();

        $totalUnits = 0;
        $totalCostValuation = 0.0;
        $totalRetailValuation = 0.0;

        $enrichedProducts = $products->map(function (Product $product) use (
            &$totalUnits,
            &$totalCostValuation,
            &$totalRetailValuation
        ) {
            $stock = max(0, (int) $product->stock);
            $costPrice = (float) $product->cost_price;
            $salePrice = (float) $product->sale_price;

            $costVal = round($stock * $costPrice, 2);
            $retailVal = round($stock * $salePrice, 2);
            $potentialProfit = round($retailVal - $costVal, 2);
            $marginPercent = $retailVal > 0 ? round(($potentialProfit / $retailVal) * 100, 1) : 0.0;

            $totalUnits += $stock;
            $totalCostValuation += $costVal;
            $totalRetailValuation += $retailVal;

            $product->cost_valuation = $costVal;
            $product->retail_valuation = $retailVal;
            $product->potential_profit = $potentialProfit;
            $product->margin_percent = $marginPercent;

            return $product;
        });

        $totalPotentialProfit = round($totalRetailValuation - $totalCostValuation, 2);
        $potentialMarginPercent = $totalRetailValuation > 0
            ? round(($totalPotentialProfit / $totalRetailValuation) * 100, 1)
            : 0.0;

        $categories = Category::where('business_id', $businessId)->orderBy('name')->get();

        return [
            'products' => $enrichedProducts,
            'categories' => $categories,
            'total_products' => $products->count(),
            'total_units' => $totalUnits,
            'total_cost_valuation' => $totalCostValuation,
            'total_retail_valuation' => $totalRetailValuation,
            'total_potential_profit' => $totalPotentialProfit,
            'potential_margin_percent' => $potentialMarginPercent,
            'selected_category_id' => $filters['category_id'] ?? '',
            'search' => $filters['search'] ?? '',
        ];
    }

    /**
     * Generate top selling products and profitability report.
     *
     * @param int $businessId
     * @param array $filters
     * @return array
     */
    public function getTopProductsReport(int $businessId, array $filters = []): array
    {
        $dateFrom = !empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = !empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : Carbon::now()->endOfDay();

        $results = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$dateFrom, $dateTo])
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.cost_price',
                'products.sale_price',
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('SUM(sale_details.subtotal) as total_revenue')
            )
            ->groupBy(
                'products.id',
                'products.name',
                'products.sku',
                'products.cost_price',
                'products.sale_price'
            )
            ->orderByDesc('total_quantity')
            ->get();

        $totalItemsSold = 0;
        $totalRevenue = 0.0;
        $totalCogs = 0.0;

        $products = $results->map(function ($item) use (&$totalItemsSold, &$totalRevenue, &$totalCogs) {
            $qty = (int) $item->total_quantity;
            $revenue = (float) $item->total_revenue;
            $costPrice = (float) $item->cost_price;

            $cogs = round($qty * $costPrice, 2);
            $grossProfit = round($revenue - $cogs, 2);
            $marginPercent = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0.0;

            $totalItemsSold += $qty;
            $totalRevenue += $revenue;
            $totalCogs += $cogs;

            $item->cogs = $cogs;
            $item->gross_profit = $grossProfit;
            $item->margin_percent = $marginPercent;

            return $item;
        });

        $totalGrossProfit = round($totalRevenue - $totalCogs, 2);
        $overallMarginPercent = $totalRevenue > 0 ? round(($totalGrossProfit / $totalRevenue) * 100, 1) : 0.0;

        return [
            'products' => $products,
            'total_items_sold' => $totalItemsSold,
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_gross_profit' => $totalGrossProfit,
            'overall_margin_percent' => $overallMarginPercent,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
        ];
    }

    /**
     * Export sales report as CSV string.
     *
     * @param int $businessId
     * @param array $filters
     * @return string
     */
    public function exportSalesCsv(int $businessId, array $filters = []): string
    {
        $data = $this->getSalesReport($businessId, $filters);
        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM for Excel compatibility
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($handle, [
            'Factura',
            'Fecha',
            'Cliente',
            'Vendedor',
            'Metodo de Pago',
            'Subtotal',
            'Descuento',
            'Total',
        ]);

        foreach ($data['sales'] as $sale) {
            $row = [
                $sale->invoice_number,
                $sale->sale_date->format('Y-m-d H:i'),
                $sale->customer ? $sale->customer->name : 'Consumidor Final',
                $sale->user ? $sale->user->name : 'N/A',
                $sale->payment_method,
                $sale->subtotal,
                $sale->discount,
                $sale->total,
            ];
            fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Export inventory valuation report as CSV string.
     *
     * @param int $businessId
     * @param array $filters
     * @return string
     */
    public function exportInventoryCsv(int $businessId, array $filters = []): string
    {
        $data = $this->getInventoryValuationReport($businessId, $filters);
        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($handle, [
            'SKU',
            'Producto',
            'Categoria',
            'Stock Actual',
            'Stock Minimo',
            'Costo Unitario',
            'Precio Venta',
            'Valoracion Costo',
            'Valoracion Venta',
            'Ganancia Potencial',
            'Margen %',
        ]);

        foreach ($data['products'] as $prod) {
            $row = [
                $prod->sku,
                $prod->name,
                $prod->category ? $prod->category->name : 'Sin categoría',
                $prod->stock,
                $prod->min_stock,
                $prod->cost_price,
                $prod->sale_price,
                $prod->cost_valuation,
                $prod->retail_valuation,
                $prod->potential_profit,
                $prod->margin_percent . '%',
            ];
            fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Sanitize cell value to prevent CSV formula injection (CWE-1236).
     */
    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
