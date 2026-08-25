<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $businesses = $user->businesses()->wherePivot('is_active', true)->get();

        if ($businesses->isEmpty()) {
            if (! $request->routeIs('businesses.create', 'businesses.store', 'logout')) {
                return redirect()->route('businesses.create')
                    ->with('info', 'Por favor, crea o registra tu primer emprendimiento para continuar.');
            }

            return $next($request);
        }

        $sessionTenantId = session('current_business_id');
        $currentBusiness = $businesses->firstWhere('id', $sessionTenantId);

        if (! $currentBusiness) {
            $currentBusiness = $businesses->first();
            session(['current_business_id' => $currentBusiness->id]);
        }

        app(TenantManager::class)->set($currentBusiness);

        view()->share('currentBusiness', $currentBusiness);
        view()->share('userBusinesses', $businesses);

        return $next($request);
    }
}
