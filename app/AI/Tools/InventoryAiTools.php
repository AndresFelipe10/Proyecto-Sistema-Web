<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Product;

class InventoryAiTools
{
    /**
     * Get products with low stock (stock <= min_stock and stock > 0) (read-only).
     */
    public function lowStock(int $businessId): AiQueryResult
    {
        $baseQuery = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0);

        $totalCount = $baseQuery->count();

        if ($totalCount === 0) {
            return new AiQueryResult(
                intent: 'low_stock_products',
                summary: "¡Excelente noticia! No tienes productos con bajo stock en este momento.",
                data: []
            );
        }

        $products = (clone $baseQuery)
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get();

        $data = $products->map(function ($p) {
            $deficit = round(max(0, (float) $p->min_stock - (float) $p->stock), 3);
            if (fmod($deficit, 1.0) === 0.0) {
                $deficit = (int) $deficit;
            }

            return [
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => $p->stock,
                'min_stock' => $p->min_stock,
                'deficit' => $deficit,
            ];
        })->toArray();

        return new AiQueryResult(
            intent: 'low_stock_products',
            summary: "Tienes {$totalCount} productos con existencias iguales o inferiores a su stock mínimo de seguridad.",
            data: $data,
            meta: ['domain' => 'inventory', 'type' => 'low_stock', 'count' => $totalCount]
        );
    }

    /**
     * Get products that are completely out of stock (stock <= 0) (read-only).
     */
    public function outOfStock(int $businessId): AiQueryResult
    {
        $baseQuery = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->where('stock', '<=', 0);

        $totalCount = $baseQuery->count();

        if ($totalCount === 0) {
            return new AiQueryResult(
                intent: 'out_of_stock_products',
                summary: "No tienes productos agotados actualmente. Todo tu catálogo cuenta con inventario disponible.",
                data: [],
                meta: ['domain' => 'inventory', 'type' => 'out_of_stock', 'count' => 0]
            );
        }

        $products = (clone $baseQuery)
            ->orderBy('name', 'asc')
            ->limit(10)
            ->get();

        $data = $products->map(fn($p) => [
            'name' => $p->name,
            'sku' => $p->sku,
            'stock' => $p->stock,
            'min_stock' => $p->min_stock,
        ])->toArray();

        return new AiQueryResult(
            intent: 'out_of_stock_products',
            summary: "Atención: Tienes {$totalCount} productos completamente agotados (stock en cero).",
            data: $data,
            meta: ['domain' => 'inventory', 'type' => 'out_of_stock', 'count' => $totalCount]
        );
    }

    /**
     * Get inventory valuation and units summary (read-only).
     */
    public function summary(int $businessId): AiQueryResult
    {
        $products = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->get();

        $totalUnits = 0.0;
        $totalCost = 0.0;
        $totalRetail = 0.0;

        foreach ($products as $p) {
            $stock = max(0.0, (float) $p->stock);
            $totalUnits += $stock;
            $totalCost += ($stock * (float) $p->cost_price);
            $totalRetail += ($stock * (float) $p->sale_price);
        }

        if (fmod($totalUnits, 1.0) === 0.0) {
            $totalUnits = (int) $totalUnits;
        }

        $potentialProfit = $totalRetail - $totalCost;
        $marginPercent = $totalRetail > 0 ? round(($potentialProfit / $totalRetail) * 100, 1) : 0.0;

        return new AiQueryResult(
            intent: 'inventory_summary',
            summary: "Tu inventario cuenta con {$totalUnits} unidades en total. Está valorizado al costo en $" .
                number_format($totalCost, 0, ',', '.') . " y con un valor comercial proyectado de $" .
                number_format($totalRetail, 0, ',', '.') . " ({$marginPercent}% de margen potencial).",
            data: [
                'total_products' => $products->count(),
                'total_units' => $totalUnits,
                'total_cost_valuation' => '$' . number_format($totalCost, 0, ',', '.'),
                'total_retail_valuation' => '$' . number_format($totalRetail, 0, ',', '.'),
                'potential_profit' => '$' . number_format($potentialProfit, 0, ',', '.'),
                'potential_margin' => $marginPercent . '%',
            ],
            meta: ['domain' => 'inventory', 'type' => 'summary']
        );
    }
}
