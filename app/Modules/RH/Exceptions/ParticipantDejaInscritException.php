<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class ParticipantDejaInscritException extends Exception
{
    public function __construct(int $employeId, int $formationId)
    {
        parent::__construct("L'employé #{$employeId} est déjà inscrit à la formation #{$formationId}.");
    }
}
