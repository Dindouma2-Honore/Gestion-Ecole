<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

use Illuminate\Support\Collection;

interface BudgetServiceContract
{
    public function creerBudgetPrevisionnel(int $anneeScolaireId, array $lignes): object;

    public function validerBudget(int $budgetId, int $validateurId): void;

    public function getConsommationCategorie(int $categorieId, int $anneeScolaireId): object;

    public function getEcartsGlobaux(int $anneeScolaireId): Collection;
}
