<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessIsRestaurant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $business = app(TenantManager::class)->get();

        if (! $business || ! $business->isRestaurant()) {
            abort(403, 'Acceso restringido a comercios tipo restaurante.');
        }

        return $next($request);
    }
}
