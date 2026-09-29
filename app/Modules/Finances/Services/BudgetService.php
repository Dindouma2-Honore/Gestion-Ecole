<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Models\User;
use App\Modules\Finances\Contracts\BudgetServiceContract;
use App\Modules\Finances\Contracts\DepenseServiceContract;
use App\Modules\Finances\Exceptions\BudgetDejaExistantException;
use App\Modules\Finances\Exceptions\BudgetNonModifiableException;
use App\Modules\Finances\Exceptions\DepenseServiceIndisponibleException;
use App\Modules\Finances\Exceptions\LignesBudgetInvalidesException;
use App\Modules\Finances\Models\Budget;
use App\Modules\Finances\Models\BudgetCategorie;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BudgetService implements BudgetServiceContract
{
    public function __construct(private AuditServiceContract $audit) {}

    public function creerBudgetPrevisionnel(int $anneeScolaireId, array $lignes): object
    {
        if (Budget::query()->where('annee_scolaire_id', $anneeScolaireId)->exists()) {
            throw new BudgetDejaExistantException($anneeScolaireId);
        }

        $lignes = collect($lignes)
            ->map(fn (array $ligne): array => [
                'categorie_id' => (int) ($ligne['categorie_id'] ?? 0),
                'montant_prevu' => (float) ($ligne['montant_prevu'] ?? 0),
            ]);

        if ($lignes->isEmpty()
            || $lignes->contains(fn (array $ligne): bool => $ligne['categorie_id'] <= 0 || $ligne['montant_prevu'] <= 0)
            || $lignes->pluck('categorie_id')->duplicates()->isNotEmpty()
            || BudgetCategorie::query()->whereIn('id', $lignes->pluck('categorie_id'))->count() !== $lignes->count()) {
            throw new LignesBudgetInvalidesException;
        }

        return DB::transaction(function () use ($anneeScolaireId, $lignes): Budget {
            $budget = Budget::create(['annee_scolaire_id' => $anneeScolaireId, 'statut' => 'brouillon']);
            $budget->lignes()->createMany($lignes->all());

            return $budget->load('lignes.categorie');
        });
    }

    public function validerBudget(int $budgetId, int $validateurId): void
    {
        if (Auth::id() !== $validateurId || ! User::query()->whereKey($validateurId)->exists()) {
            throw new AccessDeniedHttpException('Le validateur doit être l’utilisateur authentifié.');
        }

        DB::transaction(function () use ($budgetId, $validateurId): void {
            $budget = Budget::query()->lockForUpdate()->findOrFail($budgetId);
            if ($budget->statut !== 'brouillon') {
                throw new BudgetNonModifiableException($budget->id, $budget->statut);
            }

            $budget->update(['statut' => 'valide', 'valide_par' => $validateurId]);
            $this->audit->enregistrerAvecMotif($budget, "Validation du budget #{$budget->id}", 'Validation du budget prévisionnel');
        });
    }

    public function getConsommationCategorie(int $categorieId, int $anneeScolaireId): object
    {
        if (! app()->bound(DepenseServiceContract::class)) {
            throw new DepenseServiceIndisponibleException;
        }

        $montantPrevu = (float) (Budget::query()
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->first()?->lignes()
            ->where('categorie_id', $categorieId)
            ->value('montant_prevu') ?? 0);
        $montantDepense = app(DepenseServiceContract::class)
            ->getTotalDepenseParCategorie($categorieId, $anneeScolaireId);

        return (object) [
            'montant_prevu' => $montantPrevu,
            'montant_consomme' => $montantDepense,
            'pourcentage_consomme' => $montantPrevu > 0 ? round(($montantDepense / $montantPrevu) * 100, 1) : 0.0,
            'depassement' => $montantDepense > $montantPrevu,
        ];
    }

    public function getEcartsGlobaux(int $anneeScolaireId): Collection
    {
        $budget = Budget::query()->where('annee_scolaire_id', $anneeScolaireId)->with('lignes.categorie')->first();
        if ($budget === null) {
            return collect();
        }

        return $budget->lignes->map(fn ($ligne): object => (object) [
            'categorie' => $ligne->categorie->nom,
            'consommation' => $this->getConsommationCategorie($ligne->categorie_id, $anneeScolaireId),
        ]);
    }
}
