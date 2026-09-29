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
        Schema::table('grilles_salariales', function (Blueprint $table): void {
            $table->string('source')->nullable()->after('date_effet');
        });

        $montants = [
            'Junior' => ['salaire_base' => 36800, 'taux_horaire' => 1200],
            'Confirmé' => ['salaire_base' => 43400, 'taux_horaire' => 1500],
            'Senior' => ['salaire_base' => 46400, 'taux_horaire' => 1500],
            'Expert' => ['salaire_base' => 50000, 'taux_horaire' => 1500],
        ];

        foreach ($montants as $categorie => $tarif) {
            $categorieId = DB::table('categories_personnel')->where('nom', $categorie)->value('id');
            if (! $categorieId) {
                continue;
            }

            DB::table('grilles_salariales')->updateOrInsert(
                ['categorie_personnel_id' => $categorieId, 'base_calcul' => 'generale', 'matiere_id' => null, 'poste_administratif_id' => null, 'tache' => null],
                [
                    'salaire_base' => $tarif['salaire_base'],
                    'taux_horaire' => $tarif['taux_horaire'],
                    'date_effet' => '2026-09-01',
                    'source' => 'Déduit des contrats FACILG 2020–2026',
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('grilles_salariales')->where('source', 'Déduit des contrats FACILG 2020–2026')->delete();
        Schema::table('grilles_salariales', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
