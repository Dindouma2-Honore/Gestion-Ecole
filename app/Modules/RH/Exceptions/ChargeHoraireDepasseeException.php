<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class ChargeHoraireDepasseeException extends Exception
{
    public function __construct(int $enseignantId, float $chargeMax)
    {
        parent::__construct("L'affectation dépasserait la charge horaire hebdomadaire maximale de {$chargeMax}h pour l'enseignant #{$enseignantId}.");
    }
}
