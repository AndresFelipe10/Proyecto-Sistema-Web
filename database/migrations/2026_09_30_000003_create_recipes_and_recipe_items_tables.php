<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'product_id']);
            $table->index('business_id');
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_per_portion', 12, 3);
            $table->string('unit', 20);
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_id']);
            $table->index('business_id');
            $table->index('recipe_id');
            $table->index('ingredient_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE recipe_items ADD CONSTRAINT chk_recipe_items_qty CHECK (quantity_per_portion > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
    }
};
