<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Display a listing of inventory movements.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InventoryMovement::class);

        $query = InventoryMovement::with(['product', 'user']);

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('movement_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('movement_date', '<=', $dateTo);
        }

        $movements = $query->latest('movement_date')->paginate(20)->withQueryString();
        $products = Product::orderBy('name')->get();

        return view('inventory.index', [
            'movements' => $movements,
            'products' => $products,
            'selectedProduct' => $productId,
            'selectedType' => $type,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    /**
     * Show the form for creating a new inventory movement.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', InventoryMovement::class);

        $products = Product::where('is_active', true)->orderBy('name')->get();
        $selectedProductId = $request->input('product_id');

        return view('inventory.create', [
            'products' => $products,
            'selectedProductId' => $selectedProductId,
        ]);
    }

    /**
     * Store a newly created inventory movement in storage.
     */
    public function store(StoreInventoryMovementRequest $request, InventoryService $inventoryService): RedirectResponse
    {
        try {
            $movement = $inventoryService->registerMovement(
                product: (int) $request->product_id,
                type: $request->type,
                quantity: (int) $request->quantity,
                reason: $request->reason,
                userId: $request->user()->id
            );

            return redirect()->route('inventory.index')
                ->with('status', "Movimiento registrado exitosamente. Nuevo stock para '{$movement->product->name}': {$movement->new_stock} unidades.");
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors([
                'quantity' => $e->getMessage(),
            ]);
        }
    }
}
