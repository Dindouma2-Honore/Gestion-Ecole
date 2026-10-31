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
        $tousPaiements = Paiement::query()
            ->with(['fraisDivers.poste', 'annulations'])
            ->whereBetween('created_at', [$jour->startOfDay(), $jour->endOfDay()])
            ->orderByDesc('created_at')
            ->get();

        $tousPaiements->each(fn (Paiement $paiement) => $paiement->setAttribute(
            'type_frais_libelle',
            $this->libelleTypeFrais($paiement),
        ));

        $paiementsValides = $tousPaiements->where('statut', 'valide');
        $paiementsAnnules = $tousPaiements->where('statut', 'annule');

        $totalValide = round((float) $paiementsValides->sum('montant'), 2);
        $totalAnnule = round((float) $paiementsAnnules->sum('montant'), 2);
        $net = round($totalValide - $totalAnnule, 2);

        $totauxParType = $paiementsValides
            ->groupBy('type_frais_libelle')
            ->map(fn (Collection $elements): float => round((float) $elements->sum('montant'), 2))
            ->sortDesc()
            ->all();

        $totauxParMode = $paiementsValides
            ->groupBy(fn (Paiement $paiement): string => $paiement->mode ?: 'Non renseigné')
            ->map(fn (Collection $elements): float => round((float) $elements->sum('montant'), 2))
            ->sortDesc()
            ->all();

        return (object) [
            'date' => $jour->startOfDay(),
            'total' => $totalValide,
            'total_annule' => $totalAnnule,
            'net' => $net,
            'nombre_paiements' => $paiementsValides->count(),
            'nombre_annulations' => $paiementsAnnules->count(),
            'totaux_par_type' => $totauxParType,
            'totaux_par_mode' => $totauxParMode,
            'paiements' => $tousPaiements,
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
