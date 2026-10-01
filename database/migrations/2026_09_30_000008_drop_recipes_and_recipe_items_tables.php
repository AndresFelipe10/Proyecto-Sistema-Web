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
        // 1. Eliminar de forma segura tablas de recetas e insumos
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');

        // 2. Descartar checks de MySQL si existen y hacer nullable las columnas en products
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE products DROP CHECK chk_product_type');
            } catch (\Throwable $e) {}

            try {
                DB::statement('ALTER TABLE products DROP CHECK chk_base_unit');
            } catch (\Throwable $e) {}
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 20)->nullable()->default('standard')->change();
            $table->string('base_unit', 20)->nullable()->default('unit')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
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
    }
};
