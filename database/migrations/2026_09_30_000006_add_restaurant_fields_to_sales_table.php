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
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('restaurant_order_id')->nullable()->after('customer_document')->constrained('restaurant_orders')->nullOnDelete();
            $table->enum('order_type', ['retail', 'table', 'delivery', 'takeout'])->nullable()->default(null)->after('restaurant_order_id');
            $table->decimal('delivery_fee', 12, 2)->default(0.00)->after('discount_percentage');

            $table->index(['business_id', 'order_type']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_delivery_fee CHECK (delivery_fee >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE sales DROP CONSTRAINT chk_sales_delivery_fee');
            }
            $table->dropForeign(['restaurant_order_id']);
            $table->dropIndex(['business_id', 'order_type']);
            $table->dropColumn(['restaurant_order_id', 'order_type', 'delivery_fee']);
        });
    }
};
