<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\GroupeFrais;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Cycle;
use App\Modules\Socle\Models\Niveau;
use App\Modules\Socle\Models\SectionScolaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $annee = AnneeScolaire::query()->updateOrCreate(
                ['libelle' => '2026-2027'],
                ['date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active'],
            );

            $francophone = SectionScolaire::query()->firstOrCreate(
                ['code' => 'FR'], ['nom' => 'Francophone', 'description' => 'Section francophone', 'actif' => true],
            );
            $anglophone = SectionScolaire::query()->firstOrCreate(
                ['code' => 'EN'], ['nom' => 'Anglophone', 'description' => 'English section', 'actif' => true],
            );

            $cycles = collect([
                'MAT' => ['Maternelle', 1],
                'PRI' => ['Primaire', 2],
                'COL' => ['Collège', 3],
                'LYC' => ['Lycée', 4],
            ])->mapWithKeys(function (array $data, string $code): array {
                $cycle = Cycle::query()->firstOrCreate(
                    ['code' => $code], ['nom' => $data[0], 'ordre' => $data[1], 'actif' => true],
                );

                return [$code => $cycle];
            });

            $niveaux = collect([
                ['PREMAT', 'Pré-maternelle', 'MAT', 1], ['MAT', 'Maternelle', 'MAT', 2],
                ['SIL', 'SIL', 'PRI', 3], ['CP', 'CP', 'PRI', 4], ['CM1', 'CM1', 'PRI', 5], ['CM2', 'CM2', 'PRI', 6],
                ['6E', '6e', 'COL', 7], ['5E', '5e', 'COL', 8], ['4E', '4e', 'COL', 9], ['3E', '3e', 'COL', 10],
                ['2NDE', 'Seconde', 'LYC', 11], ['1ERE', 'Première', 'LYC', 12], ['TLE', 'Terminale', 'LYC', 13],
            ])->mapWithKeys(function (array $data) use ($cycles): array {
                $niveau = Niveau::query()->updateOrCreate(
                    ['code' => $data[0]],
                    ['nom' => $data[1], 'cycle_id' => $cycles[$data[2]]->id, 'ordre' => $data[3]],
                );

                return [$data[0] => $niveau];
            });

            $classes = [
                ['PREMAT-FR', 'Pré-maternelle', 'PREMAT', $francophone->id, 135000],
                ['MAT-FR', 'Maternelle', 'MAT', $francophone->id, 125000],
                ['PRE-NURSERY', 'Pre-Nursery', 'PREMAT', $anglophone->id, 135000],
                ['NURSERY', 'Nursery', 'MAT', $anglophone->id, 125000],
                ['SIL-FR', 'SIL', 'SIL', $francophone->id, 125000], ['CP-FR', 'CP', 'CP', $francophone->id, 125000],
                ['CM1-FR', 'CM1', 'CM1', $francophone->id, 135000], ['CM2-FR', 'CM2', 'CM2', $francophone->id, 135000],
                ['CLASS-1', 'Class 1', 'SIL', $anglophone->id, 125000], ['CLASS-2', 'Class 2', 'CP', $anglophone->id, 125000],
                ['CLASS-3', 'Class 3', 'CP', $anglophone->id, 125000], ['CLASS-4', 'Class 4', 'CM1', $anglophone->id, 125000],
                ['CLASS-5', 'Class 5', 'CM1', $anglophone->id, 135000], ['CLASS-6', 'Class 6', 'CM2', $anglophone->id, 135000],
                ['6E-A', '6e A', '6E', $francophone->id, 135000], ['5E-A', '5e A', '5E', $francophone->id, 135000],
                ['4E-A', '4e A', '4E', $francophone->id, 135000], ['3E-A', '3e A', '3E', $francophone->id, 135000],
                ['2NDE-A', 'Seconde A', '2NDE', $francophone->id, 135000], ['1ERE-A', 'Première A', '1ERE', $francophone->id, 135000],
                ['TLE-A', 'Terminale A', 'TLE', $francophone->id, 135000],
            ];

            foreach ($classes as [$code, $nom, $niveauCode, $sectionId, $montant]) {
                $classe = Classe::query()->updateOrCreate(
                    ['code' => $code, 'annee_scolaire_id' => $annee->id],
                    ['nom' => $nom, 'niveau_id' => $niveaux[$niveauCode]->id, 'section_id' => $sectionId,
                        'capacite_max' => str_contains($niveauCode, 'MAT') ? 30 : 40, 'statut' => 'active', 'frais' => $montant],
                );
                $configuration = ConfigurationFraisClasse::query()->updateOrCreate(
                    ['classe_id' => $classe->id, 'annee_scolaire_id' => $annee->id],
                    ['montant_total' => $montant, 'politique_validation_inscription' => 'inscription_et_premiere_tranche', 'actif' => true],
                );
                $montantTranche = (int) ($montant / 5);
                $configuration->tranches()->delete();
                $configuration->tranches()->createMany([
                    ['ordre' => 1, 'libelle' => 'Tranche 1', 'montant' => $montantTranche, 'date_echeance' => '2026-09-01', 'actif' => true],
                    ['ordre' => 2, 'libelle' => 'Tranche 2', 'montant' => $montantTranche, 'date_echeance' => '2026-11-01', 'actif' => true],
                    ['ordre' => 3, 'libelle' => 'Tranche 3', 'montant' => $montantTranche, 'date_echeance' => '2027-01-01', 'actif' => true],
                    ['ordre' => 4, 'libelle' => 'Tranche 4', 'montant' => $montantTranche, 'date_echeance' => '2027-03-01', 'actif' => true],
                    ['ordre' => 5, 'libelle' => 'Tranche 5', 'montant' => $montant - ($montantTranche * 4), 'date_echeance' => '2027-05-01', 'actif' => true],
                ]);
            }

            $groupe = GroupeFrais::query()->where('code', 'scolarite')->firstOrFail();
            foreach ($niveaux as $niveau) {
                TypeFraisRecurrent::query()->updateOrCreate(
                    ['nom' => 'Inscription', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id],
                    ['nature' => 'inscription', 'groupe_frais_id' => $groupe->id, 'montant' => 15000,
                        'ratio_tranche_1' => 50, 'actif' => true],
                );
            }

            $groupeAutres = GroupeFrais::query()->where('code', 'autres')->firstOrFail();
            foreach ([
                ['Tenue scolaire', 'tenue', 25_000],
                ['Transport scolaire', 'transport', 15_000],
                ['Full package scolaire', 'fourniture', 30_000],
                ['Cantine scolaire', 'autre', 20_000],
                ['Fournitures complémentaires', 'fourniture', 10_000],
            ] as [$nom, $categorie, $montant]) {
                CatalogueFraisDivers::query()->updateOrCreate(
                    ['nom' => $nom],
                    ['categorie' => $categorie, 'groupe_frais_id' => $groupeAutres->id,
                        'montant_defaut' => $montant, 'actif' => true],
                );
            }
        });

        $this->command?->info('Données locales Ambassadors créées ou actualisées.');
    }
}
