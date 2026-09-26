<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any team members in the current business.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can invite/add team members to the current business.
     */
    public function create(User $user): bool
    {
        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can update the role of a team member in the current business.
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->isCurrentAdmin()) {
            return false;
        }

        $businessId = session('current_business_id');

        return $model->businesses()
            ->where('businesses.id', $businessId)
            ->exists();
    }

    /**
     * Determine whether the user can deactivate a team member from the current business.
     */
    public function deactivate(User $user, User $model): bool
    {
        // Admin cannot deactivate themselves from their active business
        return $user->isCurrentAdmin() && $user->id !== $model->id;
    }
}
