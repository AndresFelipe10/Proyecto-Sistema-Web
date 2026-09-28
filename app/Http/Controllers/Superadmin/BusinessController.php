<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BusinessController extends Controller
{
    /**
     * Display a listing of businesses with search and status filtering.
     */
    public function index(Request $request): View
    {
        $query = Business::with(['users' => function ($q) {
            $q->wherePivot('is_active', true);
        }]);

        if ($search = $request->input('search')) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('nit', 'like', "%{$escaped}%")
                  ->orWhere('phone', 'like', "%{$escaped}%")
                  ->orWhere('email', 'like', "%{$escaped}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $businesses = $query->latest()->paginate(15)->withQueryString();

        return view('superadmin.businesses.index', [
            'businesses' => $businesses,
            'search' => $search,
            'selectedStatus' => $request->input('status'),
        ]);
    }

    /**
     * Show the form for creating a new business + initial administrator.
     */
    public function create(): View
    {
        return view('superadmin.businesses.create');
    }

    /**
     * Store a newly created business and its administrator transactionally.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['nullable', 'string', 'min:8'],
        ], [
            'name.required' => 'El nombre del negocio es obligatorio.',
            'admin_name.required' => 'El nombre del administrador es obligatorio.',
            'admin_email.required' => 'El correo del administrador es obligatorio.',
            'admin_email.unique' => 'Ese correo ya está registrado en la plataforma.',
            'admin_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $plainPassword = ! empty($validated['admin_password'])
            ? $validated['admin_password']
            : Str::password(16, symbols: true);

        $superadminId = auth()->id();

        $business = DB::transaction(function () use ($validated, $plainPassword, $superadminId) {
            $business = new Business();
            $business->name = $validated['name'];
            $business->nit = $validated['nit'] ?? null;
            $business->phone = $validated['phone'] ?? null;
            $business->email = $validated['email'] ?? null;
            $business->address = $validated['address'] ?? null;
            $business->status = 'active';
            $business->is_active = true;
            $business->save();

            $admin = new User();
            $admin->name = $validated['admin_name'];
            $admin->email = strtolower($validated['admin_email']);
            $admin->password = Hash::make($plainPassword);
            $admin->is_superadmin = false;
            $admin->must_change_password = true;
            $admin->save();

            $adminRole = Role::firstOrCreate(
                ['slug' => Role::ROLE_ADMIN],
                [
                    'name' => 'Administrador',
                    'description' => 'Acceso y administración total del emprendimiento',
                ]
            );

            $business->users()->attach($admin->id, [
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]);

            Log::info("Negocio #{$business->id} ({$business->name}) y administrador inicial #{$admin->id} creados por el superadministrador #{$superadminId}.");

            return $business;
        });

        session()->flash('generated_password', $plainPassword);

        return redirect()->route('superadmin.businesses.index')
            ->with('status', "Negocio '{$business->name}' y su administrador creados exitosamente.");
    }

    /**
     * Show the form for editing the business.
     */
    public function edit(Business $business): View
    {
        return view('superadmin.businesses.edit', [
            'business' => $business,
        ]);
    }

    /**
     * Update the business information.
     */
    public function update(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $business->update($validated);

        Log::info("Datos del negocio #{$business->id} ({$business->name}) actualizados por el superadministrador #" . auth()->id() . ".");

        return redirect()->route('superadmin.businesses.index')
            ->with('status', "Negocio '{$business->name}' actualizado correctamente.");
    }

    /**
     * Toggle active/inactive status (suspender / reactivar).
     */
    public function toggleStatus(Business $business): RedirectResponse
    {
        $superadminId = auth()->id();

        if ($business->status === 'active') {
            $business->status = 'inactive';
            $business->is_active = false;
            $business->save();

            Log::info("Negocio #{$business->id} ({$business->name}) suspendido por el superadministrador #{$superadminId}.");

            return back()->with('status', "Negocio '{$business->name}' suspendido exitosamente.");
        }

        $business->status = 'active';
        $business->is_active = true;
        $business->save();

        Log::info("Negocio #{$business->id} ({$business->name}) reactivado por el superadministrador #{$superadminId}.");

        return back()->with('status', "Negocio '{$business->name}' reactivado exitosamente.");
    }

    /**
     * Reset the administrator password for a business (generates temporary password).
     */
    public function resetAdminPassword(Business $business): RedirectResponse
    {
        $adminRole = Role::where('slug', Role::ROLE_ADMIN)->first();

        $admin = $business->users()
            ->wherePivot('role_id', $adminRole?->id)
            ->first();

        if (! $admin) {
            return back()->withErrors(['error' => 'No se encontró un usuario administrador para este negocio.']);
        }

        $tempPassword = Str::password(16, symbols: true);
        $admin->password = Hash::make($tempPassword);
        $admin->must_change_password = true;
        $admin->save();

        session()->flash('temp_password', $tempPassword);

        Log::info("Contraseña temporal restablecida para el usuario administrador #{$admin->id} del negocio #{$business->id} por el superadministrador #" . auth()->id() . ".");

        return back()->with('status', "Contraseña temporal restablecida para el administrador {$admin->name} ({$admin->email}).");
    }

    /**
     * View users associated with a specific business (read-only with activation toggle).
     */
    public function users(Business $business): View
    {
        $users = $business->users()->withPivot('role_id', 'is_active', 'created_at')->get();
        $roles = Role::all()->keyBy('id');

        return view('superadmin.businesses.users', [
            'business' => $business,
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Toggle a user's membership active status in the business.
     */
    public function toggleUserStatus(Business $business, User $user): RedirectResponse
    {
        $membership = $business->users()->where('users.id', $user->id)->firstOrFail();

        $newStatus = ! (bool) $membership->pivot->is_active;

        $business->users()->updateExistingPivot($user->id, [
            'is_active' => $newStatus,
        ]);

        $statusText = $newStatus ? 'activado' : 'desactivado';
        Log::info("Usuario #{$user->id} {$statusText} en el negocio #{$business->id} por el superadministrador #" . auth()->id() . ".");

        return back()->with('status', "Estado del usuario {$user->name} en {$business->name} actualizado a {$statusText}.");
    }
}
