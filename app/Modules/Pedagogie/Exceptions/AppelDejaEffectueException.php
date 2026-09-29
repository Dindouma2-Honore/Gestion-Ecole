<?php

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class AppelDejaEffectueException extends Exception
{
    public function __construct(int $seanceId)
    {
        parent::__construct(
            "L'appel a déjà été fait pour la séance #{$seanceId}. Utilisez la modification plutôt qu'un nouvel appel."
        );
    }
}
