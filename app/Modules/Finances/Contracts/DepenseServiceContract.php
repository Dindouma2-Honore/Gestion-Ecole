<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

/** Port de lecture qu'E44 devra implémenter sans exposer ses Models. */
interface DepenseServiceContract
{
    public function getTotalDepenseParCategorie(int $categorieId, int $anneeScolaireId): float;
}
