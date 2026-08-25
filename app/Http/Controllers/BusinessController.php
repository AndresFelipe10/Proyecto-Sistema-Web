<?php

namespace App\Http\Controllers;

use App\Http\Requests\Business\StoreBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\Role;
use App\Services\Tenant\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BusinessController extends Controller
{
    /**
     * Display a listing of the user's businesses.
     */
    public function index(Request $request): View
    {
        $businesses = $request->user()->businesses()->withPivot('role_id', 'is_active')->get();

        return view('businesses.index', [
            'businesses' => $businesses,
            'currentBusiness' => app(TenantManager::class)->get(),
        ]);
    }

    /**
     * Show the form for creating a new business.
     */
    public function create(): View
    {
        return view('businesses.create');
    }

    /**
     * Store a newly created business in storage.
     */
    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $user = $request->user();

        $business = Business::create($request->validated());

        $adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            [
                'name' => 'Administrador',
                'description' => 'Acceso y administración total del emprendimiento',
            ]
        );

        $business->users()->attach($user->id, [
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        session(['current_business_id' => $business->id]);
        app(TenantManager::class)->set($business);

        return redirect()->route('dashboard')
            ->with('status', "¡Emprendimiento '{$business->name}' registrado y activado correctamente!");
    }

    /**
     * Show the form for editing the specified business.
     */
    public function edit(Business $business): View
    {
        Gate::authorize('update', $business);

        return view('businesses.edit', [
            'business' => $business,
        ]);
    }

    /**
     * Update the specified business in storage.
     */
    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $business->update($request->validated());

        return redirect()->route('businesses.index')
            ->with('status', "Emprendimiento '{$business->name}' actualizado correctamente.");
    }

    /**
     * Switch active business in session.
     */
    public function switch(Request $request, Business $business): RedirectResponse
    {
        Gate::authorize('switch', $business);

        session(['current_business_id' => $business->id]);
        app(TenantManager::class)->set($business);

        return redirect()->route('dashboard')
            ->with('status', "Has cambiado al emprendimiento '{$business->name}'.");
    }
}
