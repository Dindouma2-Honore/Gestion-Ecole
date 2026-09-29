<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\BilanJournalierScolariteContract;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

class BilanJournalierScolariteService implements BilanJournalierScolariteContract
{
    public function getBilan(DateTimeInterface $date): object
    {
        $jour = CarbonImmutable::instance($date);
        $paiements = Paiement::query()
            ->with('fraisDivers.poste')
            ->whereBetween('created_at', [$jour->startOfDay(), $jour->endOfDay()])
            ->where('statut', 'valide')
            ->orderByDesc('created_at')
            ->get();
        $paiements->each(fn (Paiement $paiement) => $paiement->setAttribute(
            'type_frais_libelle',
            $this->libelleTypeFrais($paiement),
        ));

        $totauxParType = $paiements
            ->groupBy('type_frais_libelle')
            ->map(fn (Collection $elements): float => round((float) $elements->sum('montant'), 2))
            ->sortDesc()
            ->all();
        $totauxParMode = $paiements
            ->groupBy(fn (Paiement $paiement): string => $paiement->mode ?: 'Non renseigné')
            ->map(fn (Collection $elements): float => round((float) $elements->sum('montant'), 2))
            ->sortDesc()
            ->all();

        return (object) [
            'date' => $jour->startOfDay(),
            'total' => round((float) $paiements->sum('montant'), 2),
            'nombre_paiements' => $paiements->count(),
            'totaux_par_type' => $totauxParType,
            'totaux_par_mode' => $totauxParMode,
            'paiements' => $paiements,
        ];
    }

    private function libelleTypeFrais(Paiement $paiement): string
    {
        if ($paiement->fraisDivers?->poste?->nom) {
            return $paiement->fraisDivers->poste->nom;
        }

        return match ($paiement->rubrique) {
            'inscription' => 'Inscription',
            'tranche_1' => 'Première tranche',
            'tranche_2' => 'Deuxième tranche',
            'tranches_classe' => 'Frais scolaires par tranches',
            null, '' => 'Non classé',
            default => str($paiement->rubrique)->replace('_', ' ')->headline()->toString(),
        };
    }
}
