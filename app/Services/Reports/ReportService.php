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
            ->with(['customer', 'user', 'payments']);

        if (!empty($filters['payment_method'])) {
            $methodFilter = $filters['payment_method'];
            if ($methodFilter === 'mixed') {
                $query->where('payment_method', 'mixed');
            } else {
                $query->where(function ($q) use ($methodFilter) {
                    $q->where('payment_method', $methodFilter)
                      ->orWhereHas('payments', function ($sub) use ($methodFilter) {
                          $sub->where('method', $methodFilter);
                      });
                });
            }
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $sales = $query->orderByDesc('sale_date')->get();

        $totalRevenue = (float) $sales->sum('total');
        $salesCount = $sales->count();
        $averageTicket = $salesCount > 0 ? round($totalRevenue / $salesCount, 2) : 0.0;
        $totalDiscounts = (float) $sales->sum('discount');

        $methodTotals = [];
        foreach ($sales as $sale) {
            if ($sale->payments->isNotEmpty()) {
                foreach ($sale->payments as $payment) {
                    $m = $payment->method instanceof \App\Enums\PaymentMethod ? $payment->method->value : (string) $payment->method;
                    if (!isset($methodTotals[$m])) {
                        $methodTotals[$m] = ['method' => $m, 'count' => 0, 'total' => 0.0, 'sale_ids' => []];
                    }
                    $methodTotals[$m]['total'] += (float) $payment->amount;
                    if (!in_array($sale->id, $methodTotals[$m]['sale_ids'])) {
                        $methodTotals[$m]['sale_ids'][] = $sale->id;
                        $methodTotals[$m]['count']++;
                    }
                }
            } else {
                $m = (string) $sale->payment_method;
                if (!isset($methodTotals[$m])) {
                    $methodTotals[$m] = ['method' => $m, 'count' => 0, 'total' => 0.0, 'sale_ids' => []];
                }
                $methodTotals[$m]['total'] += (float) $sale->total;
                $methodTotals[$m]['count']++;
            }
        }

        $byPaymentMethod = collect($methodTotals)->map(function ($item) {
            return [
                'method' => $item['method'],
                'count' => $item['count'],
                'total' => round($item['total'], 2),
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
                $sale->customer_name ?? ($sale->customer ? $sale->customer->name : config('sales.default_customer_name', 'CONSUMIDOR FINAL')),
                $sale->user ? $sale->user->name : 'N/A',
                $sale->payment_method_label ?? $sale->payment_method,
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
     * Generate daily or monthly Cash Register balancing report (Cuadre de Caja).
     *
     * @param int $businessId
     * @param array $filters
     * @return array
     */
    public function getCashRegisterReport(int $businessId, array $filters = []): array
    {
        $periodType = ($filters['period_type'] ?? 'daily') === 'monthly' ? 'monthly' : 'daily';

        if ($periodType === 'monthly') {
            $monthStr = $filters['month'] ?? Carbon::now()->format('Y-m');
            try {
                $dateFrom = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth()->startOfDay();
                $dateTo = (clone $dateFrom)->endOfMonth()->endOfDay();
            } catch (\Exception $e) {
                $dateFrom = Carbon::now()->startOfMonth()->startOfDay();
                $dateTo = Carbon::now()->endOfMonth()->endOfDay();
                $monthStr = Carbon::now()->format('Y-m');
            }
            $selectedDate = null;
            $selectedMonth = $monthStr;
        } else {
            $dateStr = $filters['date'] ?? Carbon::today()->format('Y-m-d');
            try {
                $dateFrom = Carbon::parse($dateStr)->startOfDay();
                $dateTo = (clone $dateFrom)->endOfDay();
            } catch (\Exception $e) {
                $dateFrom = Carbon::today()->startOfDay();
                $dateTo = Carbon::today()->endOfDay();
                $dateStr = Carbon::today()->format('Y-m-d');
            }
            $selectedDate = $dateStr;
            $selectedMonth = null;
        }

        $query = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->with(['customer', 'user', 'payments']);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        $sales = $query->orderBy('sale_date', 'asc')->get();

        $byMethod = [
            'cash' => [
                'name' => 'Efectivo',
                'icon' => 'bi-cash-stack',
                'color' => 'success',
                'amount' => 0.00,
                'nominal_received' => 0.00,
                'change_given' => 0.00,
                'transactions_count' => 0,
            ],
            'transfer' => [
                'name' => 'Transferencia / Nequi',
                'icon' => 'bi-phone',
                'color' => 'info',
                'amount' => 0.00,
                'transactions_count' => 0,
            ],
            'card' => [
                'name' => 'Tarjeta',
                'icon' => 'bi-credit-card',
                'color' => 'primary',
                'amount' => 0.00,
                'transactions_count' => 0,
            ],
            'other' => [
                'name' => 'Otro',
                'icon' => 'bi-wallet2',
                'color' => 'secondary',
                'amount' => 0.00,
                'transactions_count' => 0,
            ],
        ];

        foreach ($sales as $sale) {
            if ($sale->payments->isNotEmpty()) {
                foreach ($sale->payments as $payment) {
                    $m = $payment->method instanceof \App\Enums\PaymentMethod ? $payment->method->value : (string) $payment->method;
                    if (!isset($byMethod[$m])) {
                        $byMethod[$m] = [
                            'name' => ucfirst($m),
                            'icon' => 'bi-credit-card-2-front',
                            'color' => 'secondary',
                            'amount' => 0.00,
                            'transactions_count' => 0,
                        ];
                    }
                    $amt = (float) $payment->amount;
                    $byMethod[$m]['amount'] += $amt;
                    $byMethod[$m]['transactions_count']++;

                    if ($m === 'cash') {
                        $rec = $payment->cash_received !== null ? (float) $payment->cash_received : $amt;
                        $chg = $payment->change_given !== null ? (float) $payment->change_given : 0.0;
                        $byMethod['cash']['nominal_received'] += $rec;
                        $byMethod['cash']['change_given'] += $chg;
                    }
                }
            } else {
                $m = (string) $sale->payment_method;
                if (!isset($byMethod[$m])) {
                    $byMethod[$m] = [
                        'name' => ucfirst($m),
                        'icon' => 'bi-credit-card-2-front',
                        'color' => 'secondary',
                        'amount' => 0.00,
                        'transactions_count' => 0,
                    ];
                }
                $amt = (float) $sale->total;
                $byMethod[$m]['amount'] += $amt;
                $byMethod[$m]['transactions_count']++;

                if ($m === 'cash') {
                    $byMethod['cash']['nominal_received'] += $amt;
                    $byMethod['cash']['change_given'] += 0.0;
                }
            }
        }

        $totalRevenue = (float) $sales->sum('total');
        $salesCount = $sales->count();

        // Discriminación por Canal de Venta (Salón, Domicilios, Para Llevar, Retail)
        $channels = [
            'table' => [
                'name' => 'Ventas de Salón (Mesas)',
                'icon' => 'bi-aspect-ratio',
                'color' => 'primary',
                'count' => 0,
                'total' => 0.00,
            ],
            'delivery' => [
                'name' => 'Ventas por Domicilios (Delivery)',
                'icon' => 'bi-bicycle',
                'color' => 'info',
                'count' => 0,
                'total' => 0.00,
            ],
            'takeout' => [
                'name' => 'Ventas Para Llevar (Takeout)',
                'icon' => 'bi-bag',
                'color' => 'secondary',
                'count' => 0,
                'total' => 0.00,
            ],
            'retail' => [
                'name' => 'Ventas de Mostrador / Retail',
                'icon' => 'bi-shop',
                'color' => 'success',
                'count' => 0,
                'total' => 0.00,
            ],
        ];

        $totalDeliveryFee = 0.00;

        foreach ($sales as $sale) {
            $type = $sale->order_type ?: 'retail';
            if (!isset($channels[$type])) {
                $channels[$type] = [
                    'name' => ucfirst($type),
                    'icon' => 'bi-tag',
                    'color' => 'secondary',
                    'count' => 0,
                    'total' => 0.00,
                ];
            }
            $channels[$type]['count']++;
            $channels[$type]['total'] = round($channels[$type]['total'] + (float) $sale->total, 2);

            $totalDeliveryFee = round($totalDeliveryFee + (float) ($sale->delivery_fee ?? 0.00), 2);
        }

        // Conciliación de Efectivo Físico vs Dinero Digital
        $cashInDrawer = (float) ($byMethod['cash']['amount'] ?? 0.00);
        $digitalMoney = round(
            (float) ($byMethod['transfer']['amount'] ?? 0.00) +
            (float) ($byMethod['card']['amount'] ?? 0.00) +
            (float) ($byMethod['other']['amount'] ?? 0.00),
            2
        );

        $cashiers = \App\Models\User::whereHas('businesses', function ($q) use ($businessId) {
            $q->where('businesses.id', $businessId);
        })->orderBy('name')->get();

        return [
            'sales' => $sales,
            'cashiers' => $cashiers,
            'by_method' => $byMethod,
            'channels' => $channels,
            'total_delivery_fee' => $totalDeliveryFee,
            'cash_in_drawer' => $cashInDrawer,
            'digital_money' => $digitalMoney,
            'total_revenue' => $totalRevenue,
            'sales_count' => $salesCount,
            'period_type' => $periodType,
            'selected_date' => $selectedDate,
            'selected_month' => $selectedMonth,
            'selected_user_id' => !empty($filters['user_id']) ? (int) $filters['user_id'] : null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * Export Cash Register balancing report as CSV.
     *
     * @param int $businessId
     * @param array $filters
     * @return string
     */
    public function exportCashRegisterCsv(int $businessId, array $filters = []): string
    {
        $data = $this->getCashRegisterReport($businessId, $filters);
        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Resumen de Cabecera
        $periodLabel = $data['period_type'] === 'monthly' ? 'Mensual (' . $data['selected_month'] . ')' : 'Diario (' . $data['selected_date'] . ')';
        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], ['CUADRE DE CAJA - ' . $periodLabel]));
        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], ['Total Recaudado', $data['total_revenue']]));
        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], ['Cantidad de Ventas', $data['sales_count']]));
        fputcsv($handle, []);

        // Discriminación por Método de Pago
        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], [
            'Metodo de Pago',
            'Transacciones',
            'Monto Ingresado',
            'Efectivo Recibido (Nominal)',
            'Cambio / Vueltos Entregados',
        ]));

        foreach ($data['by_method'] as $key => $method) {
            $row = [
                $method['name'],
                $method['transactions_count'],
                $method['amount'],
                $key === 'cash' ? $method['nominal_received'] : 'N/A',
                $key === 'cash' ? $method['change_given'] : 'N/A',
            ];
            fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row));
        }

        fputcsv($handle, []);

        // Detalle de Ventas
        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], [
            'Factura',
            'Fecha y Hora',
            'Cajero / Vendedor',
            'Cliente',
            'Total',
            'Metodos Aplicados',
            'Notas',
        ]));

        foreach ($data['sales'] as $sale) {
            $methodsApplied = [];
            if ($sale->payments->isNotEmpty()) {
                foreach ($sale->payments as $p) {
                    $methodsApplied[] = $p->method_label . ': $' . number_format($p->amount, 0, ',', '.') . ($p->reference ? ' (' . $p->reference . ')' : '');
                }
            } else {
                $methodsApplied[] = ($sale->payment_method_label ?? $sale->payment_method) . ': $' . number_format($sale->total, 0, ',', '.');
            }

            $row = [
                $sale->invoice_number,
                $sale->sale_date->format('Y-m-d H:i'),
                $sale->user ? $sale->user->name : 'N/A',
                $sale->customer_name ?? ($sale->customer ? $sale->customer->name : config('sales.default_customer_name', 'CONSUMIDOR FINAL')),
                $sale->total,
                implode(' | ', $methodsApplied),
                $sale->notes ?? '',
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
