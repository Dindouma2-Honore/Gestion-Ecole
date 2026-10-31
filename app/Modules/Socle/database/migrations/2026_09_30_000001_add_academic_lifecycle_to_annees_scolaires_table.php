<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annees_scolaires', function (Blueprint $table): void {
            $table->string('status', 30)->default('preparation')->after('statut');
            $table->string('period_system', 20)->default('trimester')->after('status');
        });

        DB::table('annees_scolaires')->update([
            'status' => DB::raw("CASE statut WHEN 'active' THEN 'active' WHEN 'cloturee' THEN 'cloturee' WHEN 'archivee' THEN 'archivee' ELSE 'preparation' END"),
        ]);

        // L'ancien schéma ne garantissait pas l'unicité. En cas d'historique
        // incohérent, l'année active la plus récente est conservée ; les autres
        // restent dans l'historique et deviennent clôturées.
        $activeYearIds = DB::table('annees_scolaires')
            ->where('status', 'active')
            ->orderByDesc('id')
            ->pluck('id');
        if ($activeYearIds->count() > 1) {
            DB::table('annees_scolaires')
                ->whereIn('id', $activeYearIds->slice(1))
                ->update(['status' => 'cloturee', 'statut' => 'cloturee']);
        }

        Schema::table('annees_scolaires', function (Blueprint $table): void {
            $table->unsignedTinyInteger('active_year_guard')
                ->nullable()
                ->virtualAs("CASE WHEN status = 'active' THEN 1 ELSE NULL END");
            $table->unique('active_year_guard', 'annees_scolaires_unique_active_year');
        });
    }

    public function down(): void
    {
        // Migration additive volontairement irréversible : les données et la
        // contrainte d'unicité de l'année active doivent être préservées.
    }
};
