<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\AddOrderItemsRequest;
use App\Http\Requests\Restaurant\StoreRestaurantOrderRequest;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RestaurantOrderController extends Controller
{
    /**
     * Display a list of orders.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RestaurantOrder::class);

        $orders = RestaurantOrder::with(['table', 'user', 'items'])
            ->latest()
            ->paginate(20);

        return view('restaurant.orders.index', compact('orders'));
    }

    /**
     * Show form to open a new order.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', RestaurantOrder::class);

        $tables = RestaurantTable::where('status', 'available')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        $selectedTableId = $request->query('table_id');

        return view('restaurant.orders.create', compact('tables', 'products', 'selectedTableId'));
    }

    /**
     * Open a new order on a table.
     */
    public function store(StoreRestaurantOrderRequest $request, RestaurantOrderService $orderService): RedirectResponse
    {
        $order = $orderService->openTableOrder(
            (int) session('current_business_id'),
            (int) $request->user()->id,
            $request->validated()
        );

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', "Comanda {$order->order_number} abierta exitosamente.");
    }

    /**
     * Display the order details and order management view.
     */
    public function show(RestaurantOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['table', 'user', 'items.product', 'customer']);

        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        $batches = $order->items->groupBy('batch_number');

        return view('restaurant.orders.show', compact('order', 'products', 'batches'));
    }

    /**
     * Add dishes or items to an active order.
     */
    public function addItems(AddOrderItemsRequest $request, RestaurantOrder $order, RestaurantOrderService $orderService): RedirectResponse
    {
        $orderService->addItemsToOrder($order, $request->validated('items'));

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', 'Platos agregados correctamente a la comanda.');
    }

    /**
     * Send pending items to kitchen and render the 80mm thermal ticket.
     */
    public function kitchenTicket(Request $request, RestaurantOrder $order, RestaurantOrderService $orderService): View
    {
        Gate::authorize('view', $order);

        $itemsToPrint = $orderService->sendToKitchen($order);
        $isReprint = false;

        // Si todos los ítems ya estaban impresos (reimpresión)
        if ($itemsToPrint->isEmpty()) {
            $itemsToPrint = $order->items()
                ->where('status', '!=', 'cancelled')
                ->with('product')
                ->get();
            $isReprint = true;
        }

        return view('restaurant.orders.kitchen-ticket', [
            'order' => $order,
            'itemsToPrint' => $itemsToPrint,
            'isReprint' => $isReprint,
        ]);
    }
}
