<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class MotifAnnulationRequisException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Un motif est obligatoire pour annuler un paiement.');
    }
}
