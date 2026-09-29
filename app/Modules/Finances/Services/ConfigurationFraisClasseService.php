<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Exceptions\RepartitionTranchesInvalideException;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\PaiementTrancheAllocation;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfigurationFraisClasseService implements ConfigurationFraisClasseServiceContract
{
    public function __construct(
        private ClasseServiceInterface $classes,
        private AnneeScolaireServiceContract $annees,
    ) {}

    public function configurer(int $classeId, int $anneeScolaireId, float $montantTotal, array $tranches, array $options = []): object
    {
        if (! $this->classes->existe($classeId)) {
            throw new InvalidArgumentException('La classe est invalide.');
        }
        // Une configuration historique peut être saisie pour une année non active.
        $this->annees->getAnneeScolaire($anneeScolaireId);

        $totalTranches = round(array_sum(array_map(fn (array $tranche): float => (float) $tranche['montant'], $tranches)), 2);
        if (abs(round($montantTotal, 2) - $totalTranches) > 0.001) {
            throw new RepartitionTranchesInvalideException($montantTotal, $totalTranches);
        }

        return DB::transaction(function () use ($classeId, $anneeScolaireId, $montantTotal, $tranches, $options): ConfigurationFraisClasse {
            $configuration = ConfigurationFraisClasse::query()->updateOrCreate(
                ['classe_id' => $classeId, 'annee_scolaire_id' => $anneeScolaireId],
                [
                    'frais_inscription' => round((float) ($options['frais_inscription'] ?? 0), 2),
                    'montant_total' => round($montantTotal, 2),
                    'politique_validation_inscription' => $options['politique_validation_inscription'] ?? 'frais_inscription',
                    'montant_minimum_inscription' => $options['montant_minimum_inscription'] ?? null,
                    'actif' => $options['actif'] ?? true,
                ],
            );
            $configuration->tranches()->delete();
            $configuration->tranches()->createMany(array_map(fn (array $tranche): array => [
                ...$tranche,
                'montant' => round((float) $tranche['montant'], 2),
                'actif' => $tranche['actif'] ?? true,
            ], $tranches));

            return $configuration->load('tranches');
        });
    }

    public function obtenir(int $classeId, int $anneeScolaireId): ?array
    {
        $configuration = ConfigurationFraisClasse::query()->with('tranches')
            ->where('classe_id', $classeId)->where('annee_scolaire_id', $anneeScolaireId)->first();

        return $configuration?->toArray();
    }

    public function situation(int $eleveId, int $classeId, int $anneeScolaireId): array
    {
        $configuration = ConfigurationFraisClasse::query()->with('tranches')
            ->where('classe_id', $classeId)->where('annee_scolaire_id', $anneeScolaireId)->where('actif', true)->firstOrFail();
        $totalPaye = round((float) Paiement::query()->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)->where('statut', 'valide')->sum('montant'), 2);
        $allocationsExistantes = PaiementTrancheAllocation::query()
            ->whereIn('tranche_frais_classe_id', $configuration->tranches->pluck('id'))
            ->whereHas('paiement', fn ($query) => $query
                ->where('eleve_id', $eleveId)->where('statut', 'valide'))
            ->selectRaw('tranche_frais_classe_id, SUM(montant) as total')
            ->groupBy('tranche_frais_classe_id')
            ->pluck('total', 'tranche_frais_classe_id');
        // Les paiements historiques, antérieurs aux allocations, restent répartis FIFO.
        $aAffecter = max(0.0, $totalPaye - (float) $allocationsExistantes->sum());
        $maintenant = now()->startOfDay();
        $montantRetard = 0.0;
        $details = [];

        foreach ($configuration->tranches as $tranche) {
            $payeAlloue = (float) ($allocationsExistantes[$tranche->id] ?? 0);
            $payeHistorique = min(max(0.0, (float) $tranche->montant - $payeAlloue), $aAffecter);
            $aAffecter = max(0.0, $aAffecter - $payeHistorique);
            $paye = $payeAlloue + $payeHistorique;
            $reste = round((float) $tranche->montant - $paye, 2);
            $statut = $reste <= 0 ? 'payee' : ($paye > 0 ? 'partiellement_payee' : ($tranche->date_echeance->isPast() ? 'echue' : 'a_venir'));
            if ($tranche->date_echeance->lt($maintenant)) {
                $montantRetard += $reste;
            }
            $details[] = ['id' => $tranche->id, 'ordre' => $tranche->ordre, 'libelle' => $tranche->libelle,
                'montant' => (float) $tranche->montant, 'date_echeance' => $tranche->date_echeance->toDateString(),
                'paye' => $paye, 'reste' => $reste, 'statut' => $statut];
        }

        return ['montant_total' => (float) $configuration->montant_total, 'total_paye' => min($totalPaye, (float) $configuration->montant_total),
            'reste_a_payer' => max(0.0, round((float) $configuration->montant_total - $totalPaye, 2)),
            'montant_en_retard' => round($montantRetard, 2), 'tranches' => $details];
    }
}
