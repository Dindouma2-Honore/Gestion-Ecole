<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Scolarite\Models\Classe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ClasseConfigurationService
{
    public function enregistrer(Classe $classe, array $formData): void
    {
        $fraisInscription = (float) config('scolarite.frais_inscription', 15_000);

        DB::table('classe_assistants')->where('classe_id', $classe->id)->delete();
        foreach (array_unique($formData['assistant_ids'] ?? []) as $employeId) {
            DB::table('classe_assistants')->insert(['classe_id' => $classe->id, 'employe_id' => (int) $employeId]);
        }

        $debut = CarbonImmutable::parse(DB::table('annees_scolaires')->where('id', $classe->annee_scolaire_id)->value('date_debut'));
        $tranches = [];
        for ($ordre = 1; $ordre <= 5; $ordre++) {
            $tranches[] = [
                'ordre' => $ordre,
                'libelle' => "Tranche {$ordre}",
                'montant' => (float) ($formData["tranche_{$ordre}"] ?? 0),
                'date_echeance' => $debut->addMonths(($ordre - 1) * 2)->toDateString(),
            ];
        }

        app(ConfigurationFraisClasseServiceContract::class)->configurer(
            $classe->id,
            (int) $classe->annee_scolaire_id,
            array_sum(array_column($tranches, 'montant')),
            $tranches,
            [
                'frais_inscription' => $fraisInscription,
                'politique_validation_inscription' => 'frais_inscription',
                'montant_minimum_inscription' => $fraisInscription,
            ],
        );
    }

    public function donnees(Classe $classe): array
    {
        $configuration = app(ConfigurationFraisClasseServiceContract::class)->obtenir($classe->id, (int) $classe->annee_scolaire_id);
        $donnees = [
            'assistant_ids' => DB::table('classe_assistants')->where('classe_id', $classe->id)->pluck('employe_id')->all(),
            'frais_inscription' => (float) config('scolarite.frais_inscription', 15_000),
        ];
        foreach ($configuration['tranches'] ?? [] as $tranche) {
            $donnees['tranche_'.$tranche['ordre']] = $tranche['montant'];
        }

        return $donnees;
    }
}
