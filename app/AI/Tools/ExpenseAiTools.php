<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Expense;
use Carbon\Carbon;

class ExpenseAiTools
{
    /**
     * Recent expenses summary and listing for the tenant (read-only).
     */
    public function summary(int $businessId, array $filters = []): AiQueryResult
    {
        $baseQuery = Expense::where('business_id', $businessId);
        $totalCount = $baseQuery->count();

        $monthTotal = (clone $baseQuery)
            ->whereBetween('issue_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('amount');

        if ($totalCount === 0) {
            return new AiQueryResult(
                intent: 'expenses_summary',
                summary: 'No se encontraron gastos registrados en tu negocio.',
                data: [],
                meta: ['domain' => 'expenses', 'count' => 0]
            );
        }

        $expenses = (clone $baseQuery)->with('supplier')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $data = $expenses->map(fn($e) => [
            'description' => $e->description ?: ($e->supplier ? 'Gasto: ' . $e->supplier->name : 'Gasto operativo'),
            'category' => is_object($e->category) && method_exists($e->category, 'label') 
                ? $e->category->label() 
                : (is_object($e->category) && property_exists($e->category, 'value') ? $e->category->value : (string) ($e->category ?? 'General')),
            'supplier' => $e->supplier ? $e->supplier->name : 'N/A',
            'amount' => '$' . number_format((float) $e->amount, 0, ',', '.'),
            'date' => $e->issue_date ? $e->issue_date->format('d/m/Y') : 'N/A',
        ])->toArray();

        return new AiQueryResult(
            intent: 'expenses_summary',
            summary: "Se registran gastos este mes por un valor total de $" . number_format((float)$monthTotal, 0, ',', '.') . ". A continuación se muestran los últimos {$expenses->count()} movimientos registrados.",
            data: $data,
            meta: [
                'domain' => 'expenses',
                'count' => $totalCount,
                'month_total' => $monthTotal,
            ]
        );
    }
}
