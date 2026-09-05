<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Product;

class ProductAiTools
{
    /**
     * List products in catalog for specified tenant (read-only).
     */
    public function list(int $businessId, array $filters = []): AiQueryResult
    {
        $query = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->with('category');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->limit(15)->get();

        if ($products->isEmpty()) {
            return new AiQueryResult(
                intent: 'list_products',
                summary: "No se encontraron productos activos con los criterios solicitados.",
                data: []
            );
        }

        $data = $products->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'category' => $p->category ? $p->category->name : 'Sin categoría',
            'stock' => $p->stock,
            'sale_price' => '$' . number_format($p->sale_price, 0, ',', '.'),
        ])->toArray();

        return new AiQueryResult(
            intent: 'list_products',
            summary: "Se encontraron {$products->count()} productos en el catálogo.",
            data: $data
        );
    }
}
