<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class DureeSuspensionRequiseException extends Exception
{
    public function __construct()
    {
        parent::__construct("La durée en jours est obligatoire pour une sanction de type suspension.");
    }
}
