<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        if ((int) $product->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        if ((int) $product->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        if ((int) $product->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin();
    }
}
