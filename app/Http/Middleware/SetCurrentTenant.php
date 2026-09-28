<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Si es superadmin en rutas de negocio, redirigir a /superadmin y no exponer datos
        if ($user->is_superadmin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Acceso denegado.'], 403);
            }

            return redirect()->route('superadmin.dashboard');
        }

        // Resuelve el negocio exclusivamente desde la membresía activa del usuario
        $membership = $user->businesses()->wherePivot('is_active', true)->first();

        // Validar si el usuario está inactivo, no tiene membresía activa o su negocio no está active
        $userIsActive = ! (isset($user->is_active) && ! $user->is_active);
        $businessIsActive = $membership && ($membership->status === 'active' && (bool) $membership->is_active);

        if (! $userIsActive || ! $membership || ! $businessIsActive) {
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $errorMessage = 'Tu cuenta no está activa. Contacta a soporte.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $errorMessage], 403);
            }

            return redirect()->route('login')
                ->withErrors(['email' => $errorMessage])
                ->with('error', $errorMessage);
        }

        // Fijar siempre el ID autoritativo en sesión y en TenantManager (nunca confiar en input del cliente)
        session(['current_business_id' => $membership->id]);
        app(TenantManager::class)->set($membership);

        view()->share('currentBusiness', $membership);

        return $next($request);
    }
}
