<?php

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class SeanceNonDispenseeException extends Exception
{
    public function __construct(int $seanceId, string $statutActuel)
    {
        parent::__construct(
            "Impossible de saisir la progression pour la séance #{$seanceId} : "
            ."elle n'est pas dispensée (statut actuel : '{$statutActuel}')."
        );
    }
}
