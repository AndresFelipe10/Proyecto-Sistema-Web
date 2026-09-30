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
        Schema::create('restaurant_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('order_number', 30);
            $table->enum('order_type', ['table', 'delivery', 'takeout'])->default('table');
            $table->enum('status', ['open', 'in_kitchen', 'dispatched', 'delivered', 'closed', 'cancelled'])->default('open');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->string('delivery_phone', 30)->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->string('delivery_notes', 500)->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->string('notes', 500)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'order_number']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'order_type']);
        });

        Schema::create('restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->string('notes', 255)->nullable();
            $table->enum('status', ['pending', 'kitchen', 'served', 'cancelled'])->default('pending');
            $table->boolean('printed_to_kitchen')->default(false);
            $table->unsignedInteger('batch_number')->default(1);
            $table->timestamps();

            $table->index('business_id');
            $table->index('order_id');
            $table->index('product_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE restaurant_orders ADD CONSTRAINT chk_restaurant_orders_delivery_fee CHECK (delivery_fee >= 0)');
            DB::statement('ALTER TABLE restaurant_order_items ADD CONSTRAINT chk_restaurant_order_items_qty CHECK (quantity > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_items');
        Schema::dropIfExists('restaurant_orders');
    }
};
