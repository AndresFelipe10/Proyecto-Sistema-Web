<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaleAiTools
{
    /**
     * Sales summary for today or current month (read-only).
     */
    public function summary(int $businessId, array $filters = []): AiQueryResult
    {
        $period = $filters['period'] ?? 'today';

        if ($period === 'month') {
            $dateFrom = Carbon::now()->startOfMonth();
            $dateTo = Carbon::now()->endOfMonth();
            $periodLabel = "este mes (" . Carbon::now()->translatedFormat('F Y') . ")";
        } elseif ($period === 'yesterday') {
            $dateFrom = Carbon::yesterday()->startOfDay();
            $dateTo = Carbon::yesterday()->endOfDay();
            $periodLabel = "el día de ayer";
        } else {
            $dateFrom = Carbon::today()->startOfDay();
            $dateTo = Carbon::today()->endOfDay();
            $periodLabel = "el día de hoy";
        }

        $aggregates = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(total), 0) as aggregate_total')
            ->first();

        $count = (int) ($aggregates->aggregate_count ?? 0);
        $totalRevenue = (float) ($aggregates->aggregate_total ?? 0);
        $avgTicket = $count > 0 ? round($totalRevenue / $count, 2) : 0.0;

        return new AiQueryResult(
            intent: 'sales_summary',
            summary: "En {$periodLabel} se han registrado {$count} ventas por un valor total de $" .
                number_format($totalRevenue, 0, ',', '.') . " con un ticket promedio de $" .
                number_format($avgTicket, 0, ',', '.') . ".",
            data: [
                'period' => $period,
                'sales_count' => $count,
                'total_revenue' => '$' . number_format($totalRevenue, 0, ',', '.'),
                'average_ticket' => '$' . number_format($avgTicket, 0, ',', '.'),
            ],
            meta: [
                'domain' => 'sales',
                'period' => $period,
            ]
        );
    }

    /**
     * Sales by date range or period (read-only).
     */
    public function byPeriod(int $businessId, array $filters = []): AiQueryResult
    {
        $dateFrom = Carbon::now()->startOfMonth();
        if (!empty($filters['date_from']) && is_string($filters['date_from'])) {
            try {
                $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
            } catch (\Throwable) {
                $dateFrom = Carbon::now()->startOfMonth();
            }
        }

        $dateTo = Carbon::now()->endOfDay();
        if (!empty($filters['date_to']) && is_string($filters['date_to'])) {
            try {
                $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
            } catch (\Throwable) {
                $dateTo = Carbon::now()->endOfDay();
            }
        }

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        $baseQuery = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo]);

        $totalCount = $baseQuery->count();
        $totalRevenue = (float) (clone $baseQuery)->sum('total');

        $sales = (clone $baseQuery)
            ->with('customer')
            ->orderByDesc('sale_date')
            ->limit(10)
            ->get();

        $data = $sales->map(fn($s) => [
            'invoice' => $s->invoice_number,
            'date' => $s->sale_date->format('d/m/Y H:i'),
            'customer' => $s->customer_name ?? ($s->customer ? $s->customer->name : config('sales.default_customer_name', 'CONSUMIDOR FINAL')),
            'payment_method' => $s->payment_method_label,
            'total' => '$' . number_format($s->total, 0, ',', '.'),
        ])->toArray();

        return new AiQueryResult(
            intent: 'sales_by_period',
            summary: "Se registraron ventas por un total de $" . number_format($totalRevenue, 0, ',', '.') .
                " entre {$dateFrom->format('d/m/Y')} y {$dateTo->format('d/m/Y')}.",
            data: $data,
            meta: [
                'domain' => 'sales',
                'count' => $totalCount,
            ]
        );
    }

    /**
     * Top selling products by quantity (read-only).
     */
    public function topSelling(int $businessId, array $filters = []): AiQueryResult
    {
        $limit = isset($filters['limit']) ? min(15, max(1, (int)$filters['limit'])) : 5;

        $results = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('SUM(sale_details.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        if ($results->isEmpty()) {
            return new AiQueryResult(
                intent: 'top_selling_products',
                summary: "Aún no se registran ventas completadas para calcular el ranking de productos más vendidos.",
                data: []
            );
        }

        $data = $results->map(function ($r) {
            $qty = (float) $r->total_quantity == (int) $r->total_quantity ? (int) $r->total_quantity : round((float) $r->total_quantity, 3);
            return [
                'name' => $r->name,
                'sku' => $r->sku,
                'quantity_sold' => $qty,
                'revenue_generated' => '$' . number_format($r->total_revenue, 0, ',', '.'),
            ];
        })->toArray();

        $topName = $results->first()->name;
        $rawTopQty = (float) $results->first()->total_quantity;
        $topQty = $rawTopQty == (int) $rawTopQty ? (int) $rawTopQty : round($rawTopQty, 3);

        return new AiQueryResult(
            intent: 'top_selling_products',
            summary: "Tu producto estrella es '{$topName}' con {$topQty} unidades vendidas. Aquí tienes el top {$results->count()}:",
            data: $data
        );
    }
}
