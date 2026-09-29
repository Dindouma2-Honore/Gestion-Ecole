<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class BudgetNonModifiableException extends DomainException
{
    public function __construct(int $budgetId, string $statut)
    {
        parent::__construct("Le budget #{$budgetId} au statut '{$statut}' ne peut plus être modifié ou validé.");
    }
}
