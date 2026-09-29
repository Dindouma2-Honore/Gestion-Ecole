<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class ChevauchementCongeException extends Exception
{
    public function __construct(int $employeId, \DateTimeInterface $debut, \DateTimeInterface $fin)
    {
        parent::__construct("L'employé #{$employeId} a déjà une demande de congé qui chevauche la période du {$debut->format('d/m/Y')} au {$fin->format('d/m/Y')}.");
    }
}
