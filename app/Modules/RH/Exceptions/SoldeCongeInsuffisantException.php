<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class SoldeCongeInsuffisantException extends Exception
{
    public function __construct(int $employeId, float $demande, float $solde)
    {
        parent::__construct("Solde de congé insuffisant pour l'employé #{$employeId} : {$demande} jours demandés, {$solde} jours restants.");
    }
}
