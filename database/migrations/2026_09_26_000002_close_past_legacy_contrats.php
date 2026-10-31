<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pastYears = [
            '2020/2021' => '2021-08-31',
            '2021/2022' => '2022-08-31',
            '2022/2023' => '2023-08-31',
            '2023/2024' => '2024-08-31',
            '2024/2025' => '2025-08-31',
            '2025/2026' => '2026-08-31',
        ];

        foreach ($pastYears as $year => $dateFin) {
            DB::table('contrats')
                ->where('facilg_legacy_id', 'LIKE', "%:{$year}")
                ->whereNull('date_fin')
                ->update([
                    'date_fin' => $dateFin,
                    'statut' => 'expire',
                ]);
        }

        // Pour l'année courante 2026/2027
        DB::table('contrats')
            ->where('facilg_legacy_id', 'LIKE', '%:2026/2027')
            ->whereNull('date_fin')
            ->update([
                'date_fin' => '2027-07-31',
            ]);
    }

    public function down(): void
    {
        // Pas d'inversion requise
    }
};
