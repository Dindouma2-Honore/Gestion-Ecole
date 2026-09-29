<?php

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class FactureDejaGenereeException extends Exception
{
    public function __construct(int $inscriptionId)
    {
        parent::__construct(
            "Une facture existe déjà pour l'inscription #{$inscriptionId}."
        );
    }
}
