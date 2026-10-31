<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

interface BilanFinancierClasseServiceContract
{
    /** @param list<int> $eleveIds
     *  @return array{attendu: float, encaisse: float, reste: float}
     */
    public function calculer(int $classeId, int $anneeScolaireId, array $eleveIds): array;
}
