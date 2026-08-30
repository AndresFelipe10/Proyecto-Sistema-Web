<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    /**
     * Determine whether the user can view any suppliers.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can view the supplier.
     */
    public function view(User $user, Supplier $supplier): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can create suppliers.
     */
    public function create(User $user): bool
    {
        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can update the supplier.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can delete the supplier.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->isCurrentAdmin();
    }
}
