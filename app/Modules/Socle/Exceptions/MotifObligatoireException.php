<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class MotifObligatoireException extends Exception
{
    public function __construct(string $operation)
    {
        parent::__construct("Un motif est obligatoire pour l'opération : {$operation}.");
    }
}
