<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\AddOrderItemsRequest;
use App\Http\Requests\Restaurant\StoreDeliveryOrderRequest;
use App\Http\Requests\Restaurant\StoreRestaurantOrderRequest;
use App\Models\Customer;
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

        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        return view('restaurant.orders.show', compact('order', 'products', 'batches', 'customers'));
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

    /**
     * Show form to create a delivery or takeout order.
     */
    public function createDelivery(Request $request): View
    {
        Gate::authorize('create', RestaurantOrder::class);

        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        return view('restaurant.orders.create-delivery', compact('products'));
    }

    /**
     * Store a newly created delivery or takeout order.
     */
    public function storeDelivery(StoreDeliveryOrderRequest $request, RestaurantOrderService $orderService): RedirectResponse
    {
        $order = $orderService->openDeliveryOrTakeoutOrder(
            (int) session('current_business_id'),
            (int) $request->user()->id,
            $request->validated()
        );

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', "Pedido {$order->order_number} registrado exitosamente.");
    }

    /**
     * Display the operational board for deliveries and dispatches.
     */
    public function deliveries(Request $request): View
    {
        Gate::authorize('viewAny', RestaurantOrder::class);

        $orders = RestaurantOrder::whereIn('order_type', ['delivery', 'takeout'])
            ->whereIn('status', ['open', 'in_kitchen', 'dispatched', 'delivered'])
            ->with(['items.product', 'user', 'customer'])
            ->latest()
            ->get();

        $kitchenOrders = $orders->whereIn('status', ['open', 'in_kitchen']);
        $dispatchedOrders = $orders->where('status', 'dispatched');
        $deliveredOrders = $orders->where('status', 'delivered');

        return view('restaurant.orders.deliveries', compact('kitchenOrders', 'dispatchedOrders', 'deliveredOrders'));
    }

    /**
     * Update order delivery operational status.
     */
    public function updateStatus(Request $request, RestaurantOrder $order, RestaurantOrderService $orderService): RedirectResponse
    {
        Gate::authorize('update', $order);

        $request->validate([
            'status' => ['required', 'string', 'in:open,in_kitchen,dispatched,delivered,cancelled'],
        ]);

        $orderService->updateDeliveryStatus($order, $request->input('status'));

        return back()->with('success', 'Estado del pedido actualizado correctamente.');
    }

    /**
     * Render the 80mm thermal dispatch ticket for delivery orders.
     */
    public function dispatchTicket(RestaurantOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['items.product', 'user', 'customer', 'business']);

        return view('restaurant.orders.dispatch-ticket', compact('order'));
    }

    /**
     * Issue pre-bill informational ticket (80mm) and mark order/table as billed.
     */
    public function preBill(Request $request, RestaurantOrder $order): View
    {
        Gate::authorize('view', $order);

        if (! in_array($order->status, ['closed', 'cancelled'], true)) {
            $order->update(['status' => 'billed']);

            if ($order->table_id) {
                $table = RestaurantTable::where('business_id', $order->business_id)->find($order->table_id);
                if ($table) {
                    $table->update(['status' => 'billed']);
                }
            }
        }

        $order->load(['items.product', 'user', 'table', 'business']);

        return view('restaurant.orders.pre-bill-ticket', compact('order'));
    }
}
