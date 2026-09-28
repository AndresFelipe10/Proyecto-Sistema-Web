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
            $table->string('status', 20)->default('active')->after('address');
        });

        // Backfill idempotente desde is_active
        DB::table('businesses')->where('is_active', false)->update(['status' => 'inactive']);
        DB::table('businesses')->where('is_active', true)->update(['status' => 'active']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
