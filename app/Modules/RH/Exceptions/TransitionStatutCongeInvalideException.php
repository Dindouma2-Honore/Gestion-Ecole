<?php

declare(strict_types=1);

namespace App\Modules\RH\Exceptions;

use DomainException;

class TransitionStatutCongeInvalideException extends DomainException
{
    public function __construct(string $statutActuel, string $statutDemande)
    {
        parent::__construct("Transition de congé invalide : impossible de passer du statut '{$statutActuel}' au statut '{$statutDemande}'.");
    }
}
