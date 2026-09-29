<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class CompteInactifException extends Exception
{
    public function __construct(string $statut)
    {
        parent::__construct("Ce compte est {$statut} et ne peut pas se connecter. Contactez l'administrateur.");
    }
}
