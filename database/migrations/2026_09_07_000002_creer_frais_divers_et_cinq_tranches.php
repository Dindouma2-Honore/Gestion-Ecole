<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $groupeId = DB::table('groupes_frais')->where('code', 'autres')->value('id');

            foreach ([
                ['Tenue scolaire', 'tenue', 25_000],
                ['Transport scolaire', 'transport', 15_000],
                ['Full package scolaire', 'fourniture', 30_000],
                ['Cantine scolaire', 'autre', 20_000],
                ['Fournitures complémentaires', 'fourniture', 10_000],
            ] as [$nom, $categorie, $montant]) {
                DB::table('catalogue_frais_divers')->updateOrInsert(
                    ['nom' => $nom],
                    [
                        'categorie' => $categorie,
                        'groupe_frais_id' => $groupeId,
                        'montant_defaut' => $montant,
                        'actif' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }

            DB::table('configurations_frais_classe as configuration')
                ->join('annees_scolaires as annee', 'annee.id', '=', 'configuration.annee_scolaire_id')
                ->select('configuration.id', 'configuration.montant_total', 'annee.date_debut')
                ->orderBy('configuration.id')
                ->get()
                ->each(function (object $configuration): void {
                    $totalCentimes = (int) round((float) $configuration->montant_total * 100);
                    $montantBase = intdiv($totalCentimes, 5);
                    $reste = $totalCentimes % 5;
                    $dateDebut = CarbonImmutable::parse($configuration->date_debut);

                    for ($ordre = 1; $ordre <= 5; $ordre++) {
                        $montantCentimes = $montantBase + ($ordre <= $reste ? 1 : 0);

                        DB::table('tranches_frais_classe')->updateOrInsert(
                            [
                                'configuration_frais_classe_id' => $configuration->id,
                                'ordre' => $ordre,
                            ],
                            [
                                'libelle' => "Tranche {$ordre}",
                                'montant' => $montantCentimes / 100,
                                'date_echeance' => $dateDebut->addMonths(($ordre - 1) * 2)->toDateString(),
                                'actif' => true,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ],
                        );
                    }
                });
        });
    }

    public function down(): void
    {
        // Les tranches et frais peuvent déjà avoir reçu des paiements : aucun retrait automatique.
    }
};
