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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->dateTime('sale_date');
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->enum('payment_method', ['cash', 'transfer', 'card', 'other', 'mixed'])->default('cash');
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index('user_id');
            $table->index('customer_id');
            $table->index(['business_id', 'sale_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
