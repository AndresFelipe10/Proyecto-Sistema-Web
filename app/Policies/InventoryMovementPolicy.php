<?php

namespace App\Policies;

use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementPolicy
{
    /**
     * Determine whether the user can view any inventory movements.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can view the inventory movement.
     */
    public function view(User $user, InventoryMovement $movement): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can create/register inventory movements.
     */
    public function create(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }
}
