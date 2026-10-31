<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\BilanFinancierClasseServiceContract;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\FraisDiversEleve;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Support\RemiseScolariteCalculator;

final class BilanFinancierClasseService implements BilanFinancierClasseServiceContract
{
    public function calculer(int $classeId, int $anneeScolaireId, array $eleveIds): array
    {
        $eleveIds = array_values(array_unique(array_map('intval', $eleveIds)));
        if ($eleveIds === []) {
            return ['attendu' => 0.0, 'encaisse' => 0.0, 'reste' => 0.0];
        }

        $configuration = ConfigurationFraisClasse::query()
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->first();
        $inscription = (float) ($configuration?->frais_inscription ?: config('scolarite.frais_inscription', 15_000));
        $scolariteBrute = (float) ($configuration?->montant_total ?? 0);
        $attendu = 0.0;

        foreach ($eleveIds as $eleveId) {
            $attendu += $inscription + RemiseScolariteCalculator::montantNet($eleveId, $anneeScolaireId, $scolariteBrute);
        }

        $attendu += (float) FraisDiversEleve::query()
            ->whereIn('eleve_id', $eleveIds)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('statut', '!=', 'annule')
            ->sum('montant');
        $encaisse = (float) Paiement::query()
            ->whereIn('eleve_id', $eleveIds)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('statut', 'valide')
            ->sum('montant');

        return [
            'attendu' => round($attendu, 2),
            'encaisse' => round($encaisse, 2),
            'reste' => max(0.0, round($attendu - $encaisse, 2)),
        ];
    }
}
