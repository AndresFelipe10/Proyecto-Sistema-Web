<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $tenantManager = app(TenantManager::class);

        if (! $user || ! $tenantManager->hasTenant()) {
            abort(403, 'Acceso no autorizado: se requiere un usuario autenticado y un emprendimiento activo.');
        }

        $currentRole = $user->currentRole();

        if (! $currentRole || ! in_array($currentRole->slug, $roles, true)) {
            abort(403, 'No tienes permisos suficientes para realizar esta acción en este emprendimiento.');
        }

        return $next($request);
    }
}
