<?php

namespace App\Http\Controllers;

use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Models\Business;
use App\Services\Tenant\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BusinessController extends Controller
{
    /**
     * Show the form for editing the current business ("Mi negocio").
     */
    public function edit(Request $request): View
    {
        $business = app(TenantManager::class)->get();

        Gate::authorize('update', $business);

        return view('businesses.edit', [
            'business' => $business,
        ]);
    }

    /**
     * Update the current business in storage.
     */
    public function update(UpdateBusinessRequest $request): RedirectResponse
    {
        $business = app(TenantManager::class)->get();

        Gate::authorize('update', $business);

        $business->update($request->validated());

        return redirect()->route('businesses.edit')
            ->with('status', "Datos de '{$business->name}' actualizados correctamente.");
    }
}
