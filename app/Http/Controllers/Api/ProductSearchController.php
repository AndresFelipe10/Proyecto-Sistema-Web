<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    /**
     * Search products by name or SKU for the active tenant.
     * Used by the POS sale form for autocomplete.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = Product::where('is_active', true);

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->select(['id', 'name', 'sku', 'sale_price', 'stock'])
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($products);
    }
}
