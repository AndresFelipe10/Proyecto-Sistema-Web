<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenant\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of the team members in the current business.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $currentBusiness = app(TenantManager::class)->get();
        $members = $currentBusiness->users()
            ->withPivot('role_id', 'is_active', 'created_at')
            ->get();

        $roles = Role::all()->keyBy('id');

        return view('users.index', [
            'members' => $members,
            'roles' => $roles,
            'currentBusiness' => $currentBusiness,
        ]);
    }

    /**
     * Show the form for inviting/adding a user to the business.
     */
    public function create(): View
    {
        Gate::authorize('create', User::class);

        $roles = Role::all();

        return view('users.create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly attached user in the current business.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $currentBusiness = app(TenantManager::class)->get();
        $targetUser = User::where('email', $request->email)->firstOrFail();

        $alreadyMember = $currentBusiness->users()
            ->where('users.id', $targetUser->id)
            ->exists();

        if ($alreadyMember) {
            return back()->withInput()->withErrors([
                'email' => 'Este usuario ya está vinculado a este emprendimiento.',
            ]);
        }

        $currentBusiness->users()->attach($targetUser->id, [
            'role_id' => $request->role_id,
            'is_active' => true,
        ]);

        return redirect()->route('users.index')
            ->with('status', "Usuario {$targetUser->name} ({$targetUser->email}) incorporado al equipo exitosamente.");
    }

    /**
     * Show the form for editing the specified user's role.
     */
    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        $currentBusiness = app(TenantManager::class)->get();
        $member = $currentBusiness->users()
            ->where('users.id', $user->id)
            ->withPivot('role_id', 'is_active')
            ->firstOrFail();

        $roles = Role::all();

        return view('users.edit', [
            'member' => $member,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified user's role in the current business.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $currentBusiness = app(TenantManager::class)->get();
        $adminRole = Role::where('slug', Role::ROLE_ADMIN)->first();
        $newRoleId = (int) $request->role_id;

        // Prevent sole active admin demotion
        if ($adminRole && $newRoleId !== $adminRole->id) {
            $isSoleAdmin = $currentBusiness->users()
                ->wherePivot('role_id', $adminRole->id)
                ->wherePivot('is_active', true)
                ->where('users.id', $user->id)
                ->exists()
                && $currentBusiness->users()
                    ->wherePivot('role_id', $adminRole->id)
                    ->wherePivot('is_active', true)
                    ->count() <= 1;

            if ($isSoleAdmin) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['role_id' => 'No puedes remover el único administrador del negocio.']);
            }
        }

        $currentBusiness->users()->updateExistingPivot($user->id, [
            'role_id' => $request->role_id,
        ]);

        return redirect()->route('users.index')
            ->with('status', "El rol de {$user->name} ha sido actualizado correctamente.");
    }

    /**
     * Toggle the active status of a user in the current business.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $currentBusiness = app(TenantManager::class)->get();
        $member = $currentBusiness->users()
            ->where('users.id', $user->id)
            ->withPivot('role_id', 'is_active')
            ->firstOrFail();

        $newStatus = ! (bool) $member->pivot->is_active;

        $currentBusiness->users()->updateExistingPivot($user->id, [
            'is_active' => $newStatus,
        ]);

        $message = $newStatus
            ? "El usuario {$user->name} ha sido reactivado en el emprendimiento."
            : "El usuario {$user->name} ha sido desactivado del emprendimiento.";

        return redirect()->route('users.index')->with('status', $message);
    }
}
