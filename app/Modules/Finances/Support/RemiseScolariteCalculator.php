<?php

declare(strict_types=1);

namespace App\Modules\Finances\Support;

use App\Modules\Finances\Models\RemiseExoneration;

final class RemiseScolariteCalculator
{
    public static function montantNet(int $eleveId, int $anneeScolaireId, float $montantBrut): float
    {
        $montantNet = max(0.0, $montantBrut);
        $remises = RemiseExoneration::query()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('type_frais', 'scolarite')
            ->orderBy('id')
            ->get();

        foreach ($remises as $remise) {
            $montantNet = match ($remise->type) {
                'exoneration_totale' => 0.0,
                'remise_pourcentage' => $montantNet * max(0.0, 100.0 - (float) $remise->valeur) / 100,
                'remise_montant' => max(0.0, $montantNet - (float) $remise->valeur),
                default => $montantNet,
            };
        }

        return round(max(0.0, $montantNet), 2);
    }

    private function __construct() {}
}
