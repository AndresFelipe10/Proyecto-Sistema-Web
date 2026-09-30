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
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 20)->default('standard')->after('category_id');
            $table->string('base_unit', 20)->default('unit')->after('product_type');
            $table->decimal('stock', 12, 3)->default(0)->change();
            $table->decimal('min_stock', 12, 3)->default(0)->change();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('previous_stock', 12, 3)->change();
            $table->decimal('new_stock', 12, 3)->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products ADD CONSTRAINT chk_product_type CHECK (product_type IN ('standard', 'raw_material', 'dish'))");
            DB::statement("ALTER TABLE products ADD CONSTRAINT chk_base_unit CHECK (base_unit IN ('unit', 'gram', 'milliliter'))");
        }

        // Backfill defensivo
        DB::table('products')
            ->whereNull('product_type')
            ->orWhere('product_type', '')
            ->update(['product_type' => 'standard']);

        DB::table('products')
            ->whereNull('base_unit')
            ->orWhere('base_unit', '')
            ->update(['base_unit' => 'unit']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products DROP CHECK chk_product_type');
            DB::statement('ALTER TABLE products DROP CHECK chk_base_unit');
        }

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('previous_stock')->change();
            $table->integer('new_stock')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock')->default(0)->change();
            $table->integer('min_stock')->default(0)->change();
            $table->dropColumn(['product_type', 'base_unit']);
        });
    }
};
