<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class ContratChevauchementException extends Exception
{
    public function __construct(int $employeId)
    {
        parent::__construct("L'employé #{$employeId} a déjà un contrat actif. Il faut d'abord résilier ou clôturer le contrat existant avant d'en créer un nouveau.");
    }
}
