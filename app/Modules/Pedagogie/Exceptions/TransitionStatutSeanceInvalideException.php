<?php

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class TransitionStatutSeanceInvalideException extends Exception
{
    public function __construct(string $statutActuel, string $statutDemande)
    {
        parent::__construct(
            "Transition invalide : impossible de passer du statut '{$statutActuel}' à '{$statutDemande}'."
        );
    }
}
