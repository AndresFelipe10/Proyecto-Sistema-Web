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
        } else {
            $dateFrom = Carbon::today()->startOfDay();
            $dateTo = Carbon::today()->endOfDay();
            $periodLabel = "el día de hoy";
        }

        $sales = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->get();

        $totalRevenue = (float) $sales->sum('total');
        $count = $sales->count();
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
            ]
        );
    }

    /**
     * Sales by date range or period (read-only).
     */
    public function byPeriod(int $businessId, array $filters = []): AiQueryResult
    {
        $dateFrom = !empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = !empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : Carbon::now()->endOfDay();

        $sales = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->with('customer')
            ->orderByDesc('sale_date')
            ->limit(10)
            ->get();

        $totalRevenue = (float) Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->sum('total');

        $count = $sales->count();

        $data = $sales->map(fn($s) => [
            'invoice' => $s->invoice_number,
            'date' => $s->sale_date->format('d/m/Y H:i'),
            'customer' => $s->customer ? $s->customer->name : 'Venta de Mostrador',
            'payment_method' => $s->payment_method,
            'total' => '$' . number_format($s->total, 0, ',', '.'),
        ])->toArray();

        return new AiQueryResult(
            intent: 'sales_by_period',
            summary: "Se registraron ventas por un total de $" . number_format($totalRevenue, 0, ',', '.') .
                " entre {$dateFrom->format('d/m/Y')} y {$dateTo->format('d/m/Y')}.",
            data: $data
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
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('SUM(sale_details.subtotal) as total_revenue')
            )
            ->groupBy('products.name', 'products.sku')
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

        $data = $results->map(fn($r) => [
            'name' => $r->name,
            'sku' => $r->sku,
            'quantity_sold' => (int) $r->total_quantity,
            'revenue_generated' => '$' . number_format($r->total_revenue, 0, ',', '.'),
        ])->toArray();

        $topName = $results->first()->name;
        $topQty = $results->first()->total_quantity;

        return new AiQueryResult(
            intent: 'top_selling_products',
            summary: "Tu producto estrella es '{$topName}' con {$topQty} unidades vendidas. Aquí tienes el top {$results->count()}:",
            data: $data
        );
    }
}
