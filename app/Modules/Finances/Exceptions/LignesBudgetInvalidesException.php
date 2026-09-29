<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class LignesBudgetInvalidesException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Le budget doit contenir au moins une ligne valide avec un montant positif.');
    }
}
