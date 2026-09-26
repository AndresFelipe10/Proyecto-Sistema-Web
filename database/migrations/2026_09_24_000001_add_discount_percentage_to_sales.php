<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración aditiva: agrega discount_percentage a sales.
     *
     * No modifica ni elimina la columna discount existente.
     * El porcentaje se almacena para trazabilidad histórica y reportes.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)
                  ->nullable()
                  ->default(null)
                  ->after('discount')
                  ->comment('Porcentaje de descuento aplicado (0-100)');
        });

        // CHECK constraint real en MySQL (no solo validación de Laravel)
        DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_discount_percentage CHECK (discount_percentage >= 0 AND discount_percentage <= 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop constraint first, then column
        DB::statement('ALTER TABLE sales DROP CONSTRAINT chk_discount_percentage');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('discount_percentage');
        });
    }
};
