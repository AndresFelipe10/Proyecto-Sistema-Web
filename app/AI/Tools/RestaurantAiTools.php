<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Sale;
use Carbon\Carbon;

class RestaurantAiTools
{
    /**
     * Salon tables status (free vs occupied) (read-only).
     */
    public function tablesStatus(int $businessId): AiQueryResult
    {
        $tables = RestaurantTable::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $total = $tables->count();

        if ($total === 0) {
            return new AiQueryResult(
                intent: 'restaurant_tables_status',
                summary: 'No hay mesas configuradas en el restaurante.',
                data: [],
                meta: ['domain' => 'tables', 'count' => 0]
            );
        }

        $freeCount = $tables->where('status', 'available')->count();
        $occupiedCount = $tables->whereIn('status', ['occupied', 'billed'])->count();

        $data = $tables->map(fn($t) => [
            'name' => $t->name,
            'capacity' => $t->capacity . ' personas',
            'status' => $t->status === 'available' ? 'Libre' : ($t->status === 'billed' ? 'Pre-cuenta emitida' : 'Ocupada'),
        ])->toArray();

        return new AiQueryResult(
            intent: 'restaurant_tables_status',
            summary: "El salón cuenta con {$total} mesas: {$freeCount} libres y {$occupiedCount} ocupadas.",
            data: $data,
            meta: [
                'domain' => 'tables',
                'total_tables' => $total,
                'free_count' => $freeCount,
                'occupied_count' => $occupiedCount,
            ]
        );
    }

    /**
     * Open orders currently active in the restaurant (read-only).
     */
    public function openOrders(int $businessId): AiQueryResult
    {
        $baseQuery = RestaurantOrder::where('business_id', $businessId)
            ->whereIn('status', ['open', 'in_kitchen', 'dispatched', 'billed']);

        $count = $baseQuery->count();

        if ($count === 0) {
            return new AiQueryResult(
                intent: 'restaurant_orders_open',
                summary: 'Actualmente no hay comandas ni pedidos abiertos en el restaurante.',
                data: [],
                meta: ['domain' => 'restaurant_orders', 'count' => 0, 'open_count' => 0]
            );
        }

        $orders = (clone $baseQuery)
            ->with(['table'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $data = $orders->map(fn($o) => [
            'order_number' => $o->order_number,
            'type' => $o->order_type === 'table' ? 'Mesa (' . ($o->table ? $o->table->name : 'N/A') . ')' : ($o->order_type === 'delivery' ? 'Domicilio' : 'Para llevar'),
            'status' => match($o->status) {
                'open' => 'Abierta',
                'in_kitchen' => 'En cocina',
                'dispatched' => 'Despachado',
                'billed' => 'Pre-cuenta emitida',
                default => $o->status,
            },
            'total' => '$' . number_format($o->total, 0, ',', '.'),
            'opened_at' => $o->created_at ? $o->created_at->format('H:i') : 'N/A',
        ])->toArray();

        return new AiQueryResult(
            intent: 'restaurant_orders_open',
            summary: "Hay {$count} comandas activas en proceso en este momento.",
            data: $data,
            meta: [
                'domain' => 'restaurant_orders',
                'open_count' => $count,
            ]
        );
    }

    /**
     * Delivery orders registered today (read-only).
     */
    public function deliveriesToday(int $businessId): AiQueryResult
    {
        $baseQuery = RestaurantOrder::where('business_id', $businessId)
            ->where('order_type', 'delivery')
            ->whereDate('created_at', Carbon::today())
            ->where('status', '!=', 'cancelled');

        $count = $baseQuery->count();
        $totalAmount = (float) $baseQuery->sum('total');

        if ($count === 0) {
            return new AiQueryResult(
                intent: 'restaurant_deliveries_today',
                summary: 'No se registran pedidos a domicilio para el día de hoy.',
                data: [],
                meta: ['domain' => 'deliveries', 'count' => 0]
            );
        }

        $deliveries = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $data = $deliveries->map(fn($d) => [
            'order_number' => $d->order_number,
            'customer' => $d->customer_name ?: 'Sin registrar',
            'phone' => $d->delivery_phone ?: 'N/A',
            'address' => $d->delivery_address ?: 'N/A',
            'status' => match($d->status) {
                'open' => 'Pendiente',
                'in_kitchen' => 'En preparación',
                'dispatched' => 'En camino',
                'delivered' => 'Entregado',
                'billed' => 'Facturado',
                'closed' => 'Completado',
                default => $d->status,
            },
            'total' => '$' . number_format($d->total, 0, ',', '.'),
        ])->toArray();

        return new AiQueryResult(
            intent: 'restaurant_deliveries_today',
            summary: "Hoy se han registrado {$count} pedidos a domicilio por un valor acumulado de $" . number_format($totalAmount, 0, ',', '.') . ".",
            data: $data,
            meta: [
                'domain' => 'deliveries',
                'count' => $count,
                'total_amount' => $totalAmount,
            ]
        );
    }

    /**
     * Total tips / voluntary service collected today (read-only).
     */
    public function tipsCollected(int $businessId): AiQueryResult
    {
        $stats = Sale::where('business_id', $businessId)
            ->whereDate('sale_date', Carbon::today())
            ->where('status', 'completed')
            ->where('service_fee', '>', 0)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(service_fee), 0) as total_tips')
            ->first();

        $totalTips = (float) ($stats->total_tips ?? 0.0);
        $ordersCount = (int) ($stats->orders_count ?? 0);

        return new AiQueryResult(
            intent: 'restaurant_tips_collected',
            summary: "Hoy se han recaudado $" . number_format($totalTips, 0, ',', '.') . " en propinas / servicio voluntario en {$ordersCount} pedidos atendidos.",
            data: [
                'total_tips' => '$' . number_format($totalTips, 0, ',', '.'),
                'orders_with_tip' => $ordersCount,
            ],
            meta: [
                'domain' => 'tips',
                'total_tips' => $totalTips,
                'orders_count' => $ordersCount,
            ]
        );
    }
}
