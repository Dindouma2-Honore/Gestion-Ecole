<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class NumeroIdentificationDejaExistantException extends Exception
{
    public function __construct(string $numero)
    {
        parent::__construct("Le numéro d'identification '{$numero}' est déjà attribué à un autre équipement.");
    }
}
