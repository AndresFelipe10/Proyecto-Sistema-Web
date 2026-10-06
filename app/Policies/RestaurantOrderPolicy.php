<?php

namespace App\Policies;

use App\Models\RestaurantOrder;
use App\Models\User;

class RestaurantOrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, RestaurantOrder $order): bool
    {
        if ((int) $order->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, RestaurantOrder $order): bool
    {
        if ((int) $order->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, RestaurantOrder $order): bool
    {
        if ((int) $order->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can cancel an empty order.
     */
    public function cancelEmpty(User $user, RestaurantOrder $order): bool
    {
        if ((int) $order->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can cancel an active order.
     */
    public function cancel(User $user, RestaurantOrder $order): bool
    {
        if ((int) $order->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can remove items from an active order.
     */
    public function deleteItem(User $user, RestaurantOrder $order): bool
    {
        if ((int) $order->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin();
    }
}
