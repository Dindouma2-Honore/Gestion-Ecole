<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class MatriculeDejaExistantException extends Exception
{
    public function __construct(string $matricule)
    {
        parent::__construct("Le matricule '{$matricule}' est déjà attribué à un autre employé.");
    }
}
