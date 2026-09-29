<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class ContratInexistantException extends Exception
{
    public function __construct(int $employeId)
    {
        parent::__construct("Impossible de calculer la paie : aucun contrat actif trouvé pour l'employé #{$employeId}.");
    }
}
