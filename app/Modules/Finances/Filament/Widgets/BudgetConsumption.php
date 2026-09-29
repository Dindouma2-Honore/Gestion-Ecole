<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Widgets;

use App\Modules\Finances\Contracts\BudgetServiceContract;
use App\Modules\Finances\Exceptions\DepenseServiceIndisponibleException;
use App\Modules\Finances\Models\Budget;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Widgets\Widget;
use Throwable;

class BudgetConsumption extends Widget
{
    protected string $view = 'finances::filament.widgets.budget-consumption';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        try {
            $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
        } catch (Throwable) {
            return ['budget' => null, 'lignes' => []];
        }
        $budget = Budget::query()->where('annee_scolaire_id', $anneeId)->with('lignes.categorie')->first();
        $service = app(BudgetServiceContract::class);

        $lignes = $budget?->lignes->map(function ($ligne) use ($service, $anneeId): array {
            try {
                $consommation = $service->getConsommationCategorie($ligne->categorie_id, $anneeId);

                return ['nom' => $ligne->categorie->nom, 'disponible' => true, ...((array) $consommation)];
            } catch (DepenseServiceIndisponibleException) {
                return [
                    'nom' => $ligne->categorie->nom,
                    'montant_prevu' => (float) $ligne->montant_prevu,
                    'montant_consomme' => null,
                    'pourcentage_consomme' => 0.0,
                    'depassement' => false,
                    'disponible' => false,
                ];
            }
        })->all() ?? [];

        return ['budget' => $budget, 'lignes' => $lignes];
    }
}
