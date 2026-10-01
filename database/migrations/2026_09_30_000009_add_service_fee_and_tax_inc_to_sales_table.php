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
            $table->decimal('service_fee', 12, 2)->default(0.00)->after('delivery_fee');
            $table->decimal('tax_inc', 12, 2)->default(0.00)->after('service_fee');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_service_fee CHECK (service_fee >= 0)');
            DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_tax_inc CHECK (tax_inc >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE sales DROP CONSTRAINT chk_sales_service_fee');
                DB::statement('ALTER TABLE sales DROP CONSTRAINT chk_sales_tax_inc');
            }
            $table->dropColumn(['service_fee', 'tax_inc']);
        });
    }
};
