<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class CircuitValidationIncompletException extends Exception
{
    public function __construct()
    {
        parent::__construct('Impossible de démarrer un circuit de validation sans au moins un validateur.');
    }
}
