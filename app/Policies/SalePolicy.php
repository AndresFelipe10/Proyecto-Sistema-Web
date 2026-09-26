<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Determine whether the user can view any sales.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can view the sale.
     */
    public function view(User $user, Sale $sale): bool
    {
        if ((int) $sale->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can create sales.
     */
    public function create(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can delete (cancel) the sale.
     * Only administrators can cancel sales.
     */
    public function delete(User $user, Sale $sale): bool
    {
        if ((int) $sale->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin();
    }
}
