<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'recipe_items';

    protected $fillable = [
        'business_id',
        'recipe_id',
        'ingredient_id',
        'quantity_per_portion',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_portion' => 'float',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_id');
    }
}
