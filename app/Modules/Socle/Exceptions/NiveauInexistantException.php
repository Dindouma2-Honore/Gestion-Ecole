<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class NiveauInexistantException extends Exception
{
    public function __construct(int $niveauId)
    {
        parent::__construct("Le niveau #{$niveauId} n'existe pas dans la configuration de l'établissement.");
    }
}
