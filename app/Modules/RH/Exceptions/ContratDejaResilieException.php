<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class ContratDejaResilieException extends Exception
{
    public function __construct(int $contratId)
    {
        parent::__construct("Le contrat #{$contratId} est déjà résilié.");
    }
}
