<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Determine whether the user can view the business.
     */
    public function view(User $user, Business $business): bool
    {
        return $user->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Determine whether the user can update the business.
     */
    public function update(User $user, Business $business): bool
    {
        $membership = $user->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('is_active', true)
            ->first();

        if (! $membership) {
            return false;
        }

        $role = Role::find($membership->pivot->role_id);

        return $role !== null && $role->slug === Role::ROLE_ADMIN;
    }

    /**
     * Determine whether the user can switch to the business.
     */
    public function switch(User $user, Business $business): bool
    {
        return $this->view($user, $business);
    }
}
