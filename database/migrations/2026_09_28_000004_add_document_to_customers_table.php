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
        if (! Schema::hasColumn('customers', 'document')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('document', 30)->nullable()->after('name');
            });
        }

        // Backfill idempotente desde identification_number
        DB::table('customers')
            ->whereNull('document')
            ->whereNotNull('identification_number')
            ->update([
                'document' => DB::raw('identification_number'),
            ]);

        // Aplicar índice único compuesto (business_id, document)
        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['business_id', 'document'], 'customers_business_id_document_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_business_id_document_unique');
            if (Schema::hasColumn('customers', 'document')) {
                $table->dropColumn('document');
            }
        });
    }
};
