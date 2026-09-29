<?php

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class CapaciteClasseDepasseeException extends Exception
{
    public function __construct(int $classeId)
    {
        parent::__construct("La classe #{$classeId} a atteint sa capacité maximale.");
    }
}
