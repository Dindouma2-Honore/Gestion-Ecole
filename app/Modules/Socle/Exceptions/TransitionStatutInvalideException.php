<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class TransitionStatutInvalideException extends Exception
{
    public function __construct(string $statutActuel, string $statutDemande)
    {
        parent::__construct("Transition invalide : impossible de passer du statut '{$statutActuel}' au statut '{$statutDemande}'.");
    }
}
