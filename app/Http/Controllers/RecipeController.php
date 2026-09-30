<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recipe\StoreRecipeRequest;
use App\Http\Requests\Recipe\UpdateRecipeRequest;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RecipeController extends Controller
{
    /**
     * Display a listing of recipes.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Recipe::class);

        $query = Recipe::with(['product', 'items.ingredient']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $recipes = $query->latest()->paginate(15)->withQueryString();

        return view('recipes.index', [
            'recipes' => $recipes,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new recipe.
     */
    public function create(): View
    {
        Gate::authorize('create', Recipe::class);

        $dishes = Product::where('product_type', 'dish')
            ->where('is_active', true)
            ->whereDoesntHave('recipe')
            ->orderBy('name')
            ->get();

        $ingredients = Product::where('product_type', 'raw_material')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('recipes.create', [
            'dishes' => $dishes,
            'ingredients' => $ingredients,
        ]);
    }

    /**
     * Store a newly created recipe in storage.
     */
    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $recipe = DB::transaction(function () use ($validated) {
            $recipe = Recipe::create([
                'product_id' => $validated['product_id'],
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['items'] as $item) {
                RecipeItem::create([
                    'business_id' => $recipe->business_id,
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity_per_portion' => $item['quantity_per_portion'],
                    'unit' => $item['unit'],
                ]);
            }

            return $recipe;
        });

        return redirect()->route('recipes.index')
            ->with('status', "Receta '{$recipe->name}' registrada exitosamente.");
    }

    /**
     * Display the specified recipe.
     */
    public function show(Recipe $recipe): View
    {
        Gate::authorize('view', $recipe);

        $recipe->load(['product', 'items.ingredient']);

        return view('recipes.show', [
            'recipe' => $recipe,
        ]);
    }

    /**
     * Show the form for editing the specified recipe.
     */
    public function edit(Recipe $recipe): View
    {
        Gate::authorize('update', $recipe);

        $recipe->load(['product', 'items.ingredient']);

        $dishes = Product::where('product_type', 'dish')
            ->where('is_active', true)
            ->where(function ($q) use ($recipe) {
                $q->whereDoesntHave('recipe')
                  ->orWhere('id', $recipe->product_id);
            })
            ->orderBy('name')
            ->get();

        $ingredients = Product::where('product_type', 'raw_material')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('recipes.edit', [
            'recipe' => $recipe,
            'dishes' => $dishes,
            'ingredients' => $ingredients,
        ]);
    }

    /**
     * Update the specified recipe in storage.
     */
    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($recipe, $validated) {
            $recipe->update([
                'product_id' => $validated['product_id'],
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $recipe->items()->delete();

            foreach ($validated['items'] as $item) {
                RecipeItem::create([
                    'business_id' => $recipe->business_id,
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity_per_portion' => $item['quantity_per_portion'],
                    'unit' => $item['unit'],
                ]);
            }
        });

        return redirect()->route('recipes.index')
            ->with('status', "Receta '{$recipe->name}' actualizada exitosamente.");
    }

    /**
     * Remove the specified recipe from storage.
     */
    public function destroy(Recipe $recipe): RedirectResponse
    {
        Gate::authorize('delete', $recipe);

        $recipeName = $recipe->name;
        $recipe->delete();

        return redirect()->route('recipes.index')
            ->with('status', "Receta '{$recipeName}' eliminada exitosamente.");
    }
}
