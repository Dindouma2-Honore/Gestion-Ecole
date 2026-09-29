<?php

declare(strict_types=1);

namespace App\Modules\RH\Contracts;

use Carbon\Carbon;

interface AvanceSalaireServiceContract
{
    public function demanderAvance(int $employeId, float $montant, string $motif = '', bool $derogationPlafond = false, ?string $motifDerogation = null): object;

    public function approuverAvance(int $avanceId, string $motifValidation): object;

    public function validerAvance(int $avanceId, string $motifValidation): object;

    public function getAvanceActive(int $employeId): ?object;

    public function getAvanceNonRecouvree(int $personnelId, Carbon $periode): ?float;

    public function enregistrerRecouvrement(int $avanceId, float $montant): void;
}
