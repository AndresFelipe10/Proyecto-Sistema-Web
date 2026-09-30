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
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE restaurant_orders MODIFY COLUMN status ENUM('open', 'in_kitchen', 'dispatched', 'delivered', 'billed', 'closed', 'cancelled') NOT NULL DEFAULT 'open'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE restaurant_orders MODIFY COLUMN status ENUM('open', 'in_kitchen', 'dispatched', 'delivered', 'closed', 'cancelled') NOT NULL DEFAULT 'open'");
        }
    }
};
