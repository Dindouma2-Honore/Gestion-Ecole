<?php

namespace App\Modules\Pedagogie\Contracts;

interface AffectationPedagogiqueServiceInterface
{
    public function enregistrer(array $data, ?int $id = null): object;

    public function estAutorise(int $enseignantId, int $classeId, int $matiereId, int $anneeId, string $date): bool;
}
