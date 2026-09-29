<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\BalanceDepenseProviderContract;
use App\Modules\Finances\Contracts\BalanceServiceContract;
use App\Modules\Finances\Exceptions\BalanceDepensesIndisponiblesException;
use App\Modules\Finances\Models\MouvementCaisse;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

class BalanceService implements BalanceServiceContract
{
    public function getBalance(
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
        ?string $moduleOrigine = null,
        ?string $type = null,
    ): object {
        [$debut, $fin] = $this->normaliserPeriode($dateDebut, $dateFin);

        $mouvements = MouvementCaisse::query()
            ->whereBetween('created_at', [$debut, $fin])
            ->when(filled($moduleOrigine), fn ($query) => $query->where('module_origine', $moduleOrigine))
            ->when(filled($type), fn ($query) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->get();

        $entrees = $mouvements->where('type', 'encaissement')->values();
        $sorties = $mouvements->where('type', 'decaissement')->values();
        $totalEntrees = round((float) $entrees->sum('montant'), 2);
        $totalSorties = round((float) $sorties->sum('montant'), 2);

        $recettesParGroupe = [
            'scolarite' => round((float) $entrees->where('sous_module', 'Paiements élèves')->sum('montant'), 2),
            'autres' => round((float) $entrees->whereNotIn('sous_module', ['Paiements élèves'])->sum('montant'), 2),
        ];

        return (object) [
            'periode' => ['debut' => $debut, 'fin' => $fin],
            'total_entrees' => $totalEntrees,
            'recettes_par_groupe' => $recettesParGroupe,
            'total_sorties' => $totalSorties,
            'solde_net' => round($totalEntrees - $totalSorties, 2),
            'detail_entrees' => $entrees,
            'detail_sorties' => $sorties,
            'nombre_operations' => $mouvements->count(),
            'par_module' => $this->parModule($mouvements),
        ];
    }

    public function getBalanceDuJour(): object
    {
        return $this->getBalance();
    }

    public function getSortiesEnAttenteValidation(?DateTimeInterface $dateDebut = null, ?DateTimeInterface $dateFin = null): Collection
    {
        [$debut, $fin] = $this->normaliserPeriode($dateDebut, $dateFin);

        if (! app()->bound(BalanceDepenseProviderContract::class)) {
            throw new BalanceDepensesIndisponiblesException;
        }

        return app(BalanceDepenseProviderContract::class)->getDepensesEnAttenteValidation($debut, $fin);
    }

    /** @return array<string, array{sous_modules: array<string, array{encaissements: float, decaissements: float}>, total_encaissements: float, total_decaissements: float}> */
    private function parModule(Collection $mouvements): array
    {
        $parModule = [];

        foreach ($mouvements->groupBy(fn (MouvementCaisse $mouvement): string => $mouvement->module_origine ?? 'Non classé') as $moduleOrigine => $lignes) {
            $sousModules = [];
            foreach ($lignes->groupBy(fn (MouvementCaisse $mouvement): string => $mouvement->sous_module ?? 'Non classé') as $sousModule => $lignesSousModule) {
                $sousModules[$sousModule] = [
                    'encaissements' => round((float) $lignesSousModule->where('type', 'encaissement')->sum('montant'), 2),
                    'decaissements' => round((float) $lignesSousModule->where('type', 'decaissement')->sum('montant'), 2),
                ];
            }

            $parModule[$moduleOrigine] = [
                'sous_modules' => $sousModules,
                'total_encaissements' => round((float) $lignes->where('type', 'encaissement')->sum('montant'), 2),
                'total_decaissements' => round((float) $lignes->where('type', 'decaissement')->sum('montant'), 2),
            ];
        }

        return $parModule;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function normaliserPeriode(?DateTimeInterface $dateDebut, ?DateTimeInterface $dateFin): array
    {
        $debut = CarbonImmutable::instance($dateDebut ?? now())->startOfDay();
        $fin = CarbonImmutable::instance($dateFin ?? now())->endOfDay();

        if ($debut->greaterThan($fin)) {
            return [$fin->startOfDay(), $debut->endOfDay()];
        }

        return [$debut, $fin];
    }
}
