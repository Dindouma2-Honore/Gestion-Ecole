<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use Exception;

class SessionCaisseIntrouvableException extends Exception
{
    public function __construct()
    {
        parent::__construct("Aucune session de caisse ouverte actuellement — ouvrez une session avant d'enregistrer un mouvement.");
    }
}
