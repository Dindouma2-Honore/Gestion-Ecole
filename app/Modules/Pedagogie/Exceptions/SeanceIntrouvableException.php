<?php

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class SeanceIntrouvableException extends Exception
{
    public function __construct(int $seanceId)
    {
        parent::__construct("La séance #{$seanceId} est introuvable.");
    }
}
