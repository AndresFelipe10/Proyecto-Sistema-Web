<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['business_id', 'is_active', 'name'], 'products_biz_active_name_idx');
            $table->index(['business_id', 'created_at'], 'products_biz_created_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['business_id', 'is_active', 'name'], 'categories_biz_active_name_idx');
            $table->index(['business_id', 'created_at'], 'categories_biz_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_biz_active_name_idx');
            $table->dropIndex('products_biz_created_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_biz_active_name_idx');
            $table->dropIndex('categories_biz_created_idx');
        });
    }
};
