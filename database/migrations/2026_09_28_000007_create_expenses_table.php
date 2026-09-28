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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('category', 30);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['paid', 'pending'])->default('pending');
            $table->date('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_original_name', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();

            // Índices de filtrado y consulta
            $table->index(['business_id', 'issue_date']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'supplier_id']);

            // Unicidad para evitar duplicidad de factura por proveedor dentro de un tenant
            $table->unique(['business_id', 'supplier_id', 'invoice_number'], 'expenses_biz_sup_inv_unique');
        });

        // Constraint de monto positivo para MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE expenses ADD CONSTRAINT chk_expenses_amount CHECK (amount > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
