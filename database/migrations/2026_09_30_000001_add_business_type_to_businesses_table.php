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
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('business_type', 20)->default('retail')->after('name');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE businesses ADD CONSTRAINT chk_business_type CHECK (business_type IN ('retail', 'restaurant'))");
        }

        // Backfill defensivo
        DB::table('businesses')
            ->whereNull('business_type')
            ->orWhere('business_type', '')
            ->update(['business_type' => 'retail']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE businesses DROP CHECK chk_business_type');
        }

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
