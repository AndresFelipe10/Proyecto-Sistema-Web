<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $query = Product::with('category');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        $tenant = app(\App\Services\Tenant\TenantManager::class)->get();
        $isRestaurant = $tenant?->isRestaurant() ?? false;
        $tab = $request->input('tab', 'all');

        if ($isRestaurant) {
            if ($tab === 'menu') {
                $query->where('product_type', 'dish');
            } elseif ($tab === 'merchandise') {
                $query->where('product_type', 'standard');
            }
        }

        $products = $query->latest()->paginate(15)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'selectedCategory' => $categoryId,
            'selectedStatus' => $request->input('status'),
            'lowStockOnly' => $request->boolean('low_stock'),
            'activeTab' => $tab,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        Gate::authorize('create', Product::class);

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('products.create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['sku'])) {
            $tenantId = app(\App\Services\Tenant\TenantManager::class)->id();
            $data['sku'] = Product::generateUniqueSku($tenantId, $data['product_type'] ?? 'standard');
        }

        $product = Product::create($data);

        return redirect()->route('products.index')
            ->with('status', "Producto '{$product->name}' (SKU: {$product->sku}) registrado exitosamente.");
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): View
    {
        Gate::authorize('view', $product);

        $product->load(['category', 'inventoryMovements' => function ($q) {
            $q->latest()->take(10);
        }]);

        return view('products.show', [
            'product' => $product,
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('products.edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')
            ->with('status', "Producto '{$product->name}' actualizado correctamente.");
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        if ($product->saleDetails()->exists()) {
            $product->update(['is_active' => false]);

            return redirect()->route('products.index')
                ->with('info', "El producto '{$product->name}' no se puede eliminar porque tiene ventas registradas. Ha sido desactivado.");
        }

        $name = $product->name;
        $product->delete();

        return redirect()->route('products.index')
            ->with('status', "Producto '{$name}' eliminado correctamente.");
    }
}
