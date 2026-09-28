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
            if (! Schema::hasColumn('sales', 'customer_name')) {
                $table->string('customer_name', 150)->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('sales', 'customer_document')) {
                $table->string('customer_document', 30)->nullable()->after('customer_name');
            }
        });

        $defaultName = config('sales.default_customer_name', 'CONSUMIDOR FINAL');
        $defaultDocument = config('sales.default_customer_document', '222222222222');

        // Backfill para ventas con cliente asignado
        $salesWithCustomer = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->whereNull('sales.customer_name')
            ->select('sales.id as sale_id', 'customers.name', DB::raw('COALESCE(customers.document, customers.identification_number) as doc'))
            ->get();

        foreach ($salesWithCustomer as $row) {
            DB::table('sales')->where('id', $row->sale_id)->update([
                'customer_name' => $row->name,
                'customer_document' => $row->doc,
            ]);
        }

        // Backfill para ventas sin cliente asignado (consumidor final)
        DB::table('sales')
            ->whereNull('customer_name')
            ->update([
                'customer_name' => $defaultName,
                'customer_document' => $defaultDocument,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'customer_document')) {
                $table->dropColumn('customer_document');
            }
            if (Schema::hasColumn('sales', 'customer_name')) {
                $table->dropColumn('customer_name');
            }
        });
    }
};
