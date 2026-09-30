<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreRestaurantTableRequest;
use App\Http\Requests\Restaurant\UpdateRestaurantTableRequest;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RestaurantTableController extends Controller
{
    /**
     * Display the visual salon map and tables.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RestaurantTable::class);

        $tables = RestaurantTable::with([
            'activeOrder.user',
            'activeOrder.items',
        ])
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $tables->count(),
            'available' => $tables->where('status', 'available')->where('is_active', true)->count(),
            'occupied' => $tables->where('status', 'occupied')->count(),
            'billed' => $tables->where('status', 'billed')->count(),
        ];

        return view('restaurant.tables.index', compact('tables', 'stats'));
    }

    /**
     * Show form to create a new table.
     */
    public function create(): View
    {
        Gate::authorize('create', RestaurantTable::class);

        return view('restaurant.tables.create');
    }

    /**
     * Store a newly created table.
     */
    public function store(StoreRestaurantTableRequest $request): RedirectResponse
    {
        RestaurantTable::create($request->validated());

        return redirect()->route('restaurant.tables.index')
            ->with('success', 'Mesa creada exitosamente.');
    }

    /**
     * Show form to edit a table.
     */
    public function edit(RestaurantTable $table): View
    {
        Gate::authorize('update', $table);

        return view('restaurant.tables.edit', compact('table'));
    }

    /**
     * Update the table details.
     */
    public function update(UpdateRestaurantTableRequest $request, RestaurantTable $table): RedirectResponse
    {
        $table->update($request->validated());

        return redirect()->route('restaurant.tables.index')
            ->with('success', 'Mesa actualizada correctamente.');
    }

    /**
     * Remove the table.
     */
    public function destroy(RestaurantTable $table): RedirectResponse
    {
        Gate::authorize('delete', $table);

        if ($table->isOccupied()) {
            return redirect()->route('restaurant.tables.index')
                ->with('error', 'No es posible eliminar una mesa que actualmente tiene una comanda abierta.');
        }

        $table->delete();

        return redirect()->route('restaurant.tables.index')
            ->with('success', 'Mesa eliminada correctamente.');
    }
}
