<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

use DateTimeInterface;
use Illuminate\Support\Collection;

interface BalanceServiceContract
{
    public function getBalance(
        ?DateTimeInterface $dateDebut = null,
        ?DateTimeInterface $dateFin = null,
        ?string $moduleOrigine = null,
        ?string $type = null,
    ): object;

    public function getBalanceDuJour(): object;

    public function getSortiesEnAttenteValidation(?DateTimeInterface $dateDebut = null, ?DateTimeInterface $dateFin = null): Collection;
}
