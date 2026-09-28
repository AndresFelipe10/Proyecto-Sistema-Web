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
        // 1. Modificar columna payment_method en sales para permitir 'mixed' de forma aditiva
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN payment_method ENUM('cash', 'transfer', 'card', 'other', 'mixed') NOT NULL DEFAULT 'cash'");
        }

        // 2. Crear tabla sale_payments
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 60)->nullable();
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('change_given', 12, 2)->nullable();
            $table->timestamps();

            $table->index('sale_id');
            $table->index('business_id');
            $table->index(['business_id', 'method']);
        });

        // 3. CHECK constraints reales (MySQL)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sale_payments ADD CONSTRAINT chk_sale_payments_amount CHECK (amount > 0)');
            DB::statement('ALTER TABLE sale_payments ADD CONSTRAINT chk_sale_payments_cash_received CHECK (cash_received IS NULL OR cash_received >= amount)');
        }

        // 4. Backfill idempotente para ventas históricas existentes
        $existingSales = DB::table('sales')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('sale_payments')
                    ->whereColumn('sale_payments.sale_id', 'sales.id');
            })
            ->where('total', '>', 0)
            ->select('id', 'business_id', 'payment_method', 'total', 'created_at', 'updated_at')
            ->get();

        $now = now();
        $records = [];
        foreach ($existingSales as $sale) {
            $method = in_array($sale->payment_method, ['cash', 'card', 'transfer', 'other']) 
                ? $sale->payment_method 
                : 'cash';
            $isCash = ($method === 'cash');

            $records[] = [
                'business_id' => $sale->business_id,
                'sale_id' => $sale->id,
                'method' => $method,
                'amount' => $sale->total,
                'reference' => null,
                'cash_received' => $isCash ? $sale->total : null,
                'change_given' => $isCash ? 0.00 : null,
                'created_at' => $sale->created_at ?? $now,
                'updated_at' => $sale->updated_at ?? $now,
            ];

            if (count($records) >= 100) {
                DB::table('sale_payments')->insert($records);
                $records = [];
            }
        }

        if (!empty($records)) {
            DB::table('sale_payments')->insert($records);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY COLUMN payment_method ENUM('cash', 'transfer', 'card', 'other') NOT NULL DEFAULT 'cash'");
        }
    }
};
