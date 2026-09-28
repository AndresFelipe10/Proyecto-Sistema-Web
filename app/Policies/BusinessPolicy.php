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
        if ((int) $business->id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Determine whether the user can update the business ("Mi negocio").
     */
    public function update(User $user, Business $business): bool
    {
        if ((int) $business->id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isAdminOf($business);
    }
}
