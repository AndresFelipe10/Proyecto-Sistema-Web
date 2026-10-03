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
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RestaurantOrderController extends Controller
{
    /**
     * Display a list of orders separated by temporal hierarchy (today vs past).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RestaurantOrder::class);

        $todayBogota = now('America/Bogota');
        $todayDate = $todayBogota->toDateString();
        $todayStartUtc = (clone $todayBogota)->startOfDay()->utc();
        $todayEndUtc = (clone $todayBogota)->endOfDay()->utc();

        // 1. Comandas del día (Hoy en zona horaria America/Bogota)
        $todayOrders = RestaurantOrder::with(['table', 'user', 'items', 'customer'])
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])
            ->latest()
            ->get();

        // 2. Comandas anteriores a hoy con filtros opcionales
        $pastQuery = RestaurantOrder::with(['table', 'user', 'items', 'customer'])
            ->where('created_at', '<', $todayStartUtc);

        if ($search = trim((string) $request->input('search', ''))) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $pastQuery->where(function ($q) use ($escaped) {
                $q->whereRaw("order_number LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                  ->orWhereRaw("customer_name LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                  ->orWhereHas('table', function ($tq) use ($escaped) {
                      $tq->whereRaw("name LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
                  });
            });
        }

        if ($status = $request->input('status')) {
            $pastQuery->where('status', $status);
        }

        if ($dateFrom = $request->input('date_from')) {
            $dateFromUtc = Carbon::parse($dateFrom, 'America/Bogota')->startOfDay()->utc();
            $pastQuery->where('created_at', '>=', $dateFromUtc);
        }

        if ($dateTo = $request->input('date_to')) {
            $dateToUtc = Carbon::parse($dateTo, 'America/Bogota')->endOfDay()->utc();
            $pastQuery->where('created_at', '<=', $dateToUtc);
        }

        $pastOrders = $pastQuery->latest()->paginate(15, ['*'], 'past_page')->withQueryString();

        // Compatibilidad hacia atrás
        $orders = $pastOrders;

        return view('restaurant.orders.index', compact('todayOrders', 'pastOrders', 'todayDate', 'orders'));
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
            ->where('product_type', 'dish')
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
        $userId = (int) (auth()->id() ?? $request->user()->id);
        $order = $orderService->openTableOrder(
            (int) session('current_business_id'),
            $userId,
            $request->validated()
        );

        $response = redirect()->route('restaurant.orders.show', $order)
            ->with('success', "Comanda {$order->order_number} abierta exitosamente.");

        if ($order->items()->exists()) {
            $orderService->sendToKitchen($order);
            $response->with('print_kitchen_ticket_id', $order->id);
        }

        return $response;
    }

    /**
     * Display the order details and order management view.
     */
    public function show(RestaurantOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['table', 'user', 'items.product', 'customer']);

        $productsQuery = Product::where('is_active', true);
        if ($order->order_type === 'table') {
            $productsQuery->where('product_type', 'dish');
        }
        $products = $productsQuery
            ->with('category')
            ->orderBy('name')
            ->get();

        $groupedProducts = $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');

        $batches = $order->items->groupBy('batch_number');

        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        return view('restaurant.orders.show', compact('order', 'products', 'groupedProducts', 'batches', 'customers'));
    }

    /**
     * Add dishes or items to an active order.
     */
    public function addItems(AddOrderItemsRequest $request, RestaurantOrder $order, RestaurantOrderService $orderService): RedirectResponse
    {
        Gate::authorize('update', $order);

        $orderService->addItemsToOrder($order, $request->validated('items'));

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', 'Platos agregados correctamente a la comanda.')
            ->with('print_kitchen_ticket_id', $order->id);
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
        $userId = (int) (auth()->id() ?? $request->user()->id);
        $order = $orderService->openDeliveryOrTakeoutOrder(
            (int) session('current_business_id'),
            $userId,
            $request->validated()
        );

        // Enviar automáticamente a cocina los platos agregados al registrar el pedido
        $orderService->sendToKitchen($order);

        return redirect()->route('restaurant.orders.deliveries')
            ->with('success', "Pedido {$order->order_number} registrado exitosamente.")
            ->with('print_kitchen_ticket_id', $order->id);
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

    /**
     * Cancel an empty order and immediately release its table.
     */
    public function cancelEmpty(RestaurantOrder $order, RestaurantOrderService $orderService): RedirectResponse
    {
        Gate::authorize('cancelEmpty', $order);

        if ((int) $order->business_id !== (int) session('current_business_id')) {
            abort(403);
        }

        if ($order->status !== 'open') {
            return back()->with('error', 'Solo se pueden cancelar comandas en estado abierto.');
        }

        if ($order->items()->count() > 0) {
            return back()->with('error', 'No se puede cancelar una comanda que contiene platos comandados. Anule o elimine los consumos primero.');
        }

        $orderService->cancelEmptyOrder($order);

        return redirect()->route('restaurant.orders.index')
            ->with('status', "Comanda {$order->order_number} cancelada exitosamente y mesa liberada.");
    }
}
