<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
{
    /**
     * Determine whether the user can view any recipes.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can view the recipe.
     */
    public function view(User $user, Recipe $recipe): bool
    {
        if ((int) $recipe->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin() || $user->isCurrentEmployee();
    }

    /**
     * Determine whether the user can create recipes.
     */
    public function create(User $user): bool
    {
        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can update the recipe.
     */
    public function update(User $user, Recipe $recipe): bool
    {
        if ((int) $recipe->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can delete the recipe.
     */
    public function delete(User $user, Recipe $recipe): bool
    {
        if ((int) $recipe->business_id !== (int) session('current_business_id')) {
            return false;
        }

        return $user->isCurrentAdmin();
    }
}
