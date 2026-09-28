<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * Display a listing of the suppliers.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Supplier::class);

        $query = Supplier::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('identification_number', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $suppliers = $query->latest()->paginate(15)->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'search' => $search,
            'selectedStatus' => $request->input('status'),
        ]);
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('suppliers.create');
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()->route('suppliers.index')
            ->with('status', "Proveedor '{$supplier->name}' registrado exitosamente.");
    }

    /**
     * Display the specified supplier.
     */
    public function show(Supplier $supplier): View
    {
        Gate::authorize('view', $supplier);

        $pendingAmount = 0.0;
        $totalExpensesAmount = 0.0;

        if (auth()->check() && auth()->user()->isCurrentAdmin()) {
            $supplier->load(['expenses' => function ($q) {
                $q->latest('issue_date')->latest('id');
            }]);
            $pendingAmount = (float) $supplier->expenses->where('status', 'pending')->sum('amount');
            $totalExpensesAmount = (float) $supplier->expenses->sum('amount');
        }

        return view('suppliers.show', [
            'supplier' => $supplier,
            'pendingAmount' => $pendingAmount,
            'totalExpensesAmount' => $totalExpensesAmount,
        ]);
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Supplier $supplier): View
    {
        Gate::authorize('update', $supplier);

        return view('suppliers.edit', [
            'supplier' => $supplier,
        ]);
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')
            ->with('status', "Proveedor '{$supplier->name}' actualizado correctamente.");
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('delete', $supplier);

        $name = $supplier->name;
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('status', "Proveedor '{$name}' eliminado correctamente.");
    }
}
