<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class BudgetDejaExistantException extends DomainException
{
    public function __construct(int $anneeScolaireId)
    {
        parent::__construct("Un budget existe déjà pour l'année scolaire #{$anneeScolaireId}.");
    }
}
