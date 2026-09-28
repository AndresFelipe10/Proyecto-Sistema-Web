<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
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
            $table->dateTime('subscription_starts_at')->nullable()->after('status');
            $table->dateTime('subscription_ends_at')->nullable()->index()->after('subscription_starts_at');
        });

        // Backfill idempotente para negocios existentes sin fecha de suscripción
        $now = Carbon::now('America/Bogota');
        $businesses = DB::table('businesses')->whereNull('subscription_ends_at')->get();

        foreach ($businesses as $b) {
            $createdAt = $b->created_at ? Carbon::parse($b->created_at, 'America/Bogota') : $now->copy();
            $endsAt = $createdAt->copy()->addDays(30);

            // Si ya expiró al momento del despliegue, asignar now + 30 días para no alertar de golpe a tenants activos
            if ($endsAt->isPast()) {
                $endsAt = $now->copy()->addDays(30);
            }

            DB::table('businesses')->where('id', $b->id)->update([
                'subscription_starts_at' => $createdAt,
                'subscription_ends_at' => $endsAt,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['subscription_ends_at']);
            $table->dropColumn(['subscription_starts_at', 'subscription_ends_at']);
        });
    }
};
