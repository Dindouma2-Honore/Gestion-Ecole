<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

interface FacturePreinscriptionServiceContract
{
    public function renvoyerFactureProvisoire(int $factureId): object;

    public function confirmerVersement(int $factureId, string $mode, ?string $referenceTransaction = null, ?float $montant = null): object;
}
