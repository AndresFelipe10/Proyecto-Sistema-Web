<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $query = Customer::withCount('sales');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('identification_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $customers = $query->latest()->paginate(15)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
            'selectedStatus' => $request->input('status'),
        ]);
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('customers.create');
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return redirect()->route('customers.index')
            ->with('status', "Cliente '{$customer->name}' registrado exitosamente.");
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);

        $customer->load(['sales' => function ($q) {
            $q->latest('sale_date')->take(10);
        }]);

        return view('customers.show', [
            'customer' => $customer,
        ]);
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('status', "Cliente '{$customer->name}' actualizado correctamente.");
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        if ($customer->sales()->exists()) {
            $customer->update(['is_active' => false]);

            return redirect()->route('customers.index')
                ->with('info', "El cliente '{$customer->name}' tiene ventas registradas. Ha sido desactivado en lugar de eliminado para conservar el historial.");
        }

        $name = $customer->name;
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('status', "Cliente '{$name}' eliminado correctamente.");
    }
}
