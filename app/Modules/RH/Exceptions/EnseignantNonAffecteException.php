<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class EnseignantNonAffecteException extends Exception
{
    public function __construct(int $enseignantId, int $matiereId, int $classeId)
    {
        parent::__construct("L'enseignant #{$enseignantId} n'est pas affecté à la matière #{$matiereId} pour la classe #{$classeId} sur l'année scolaire en cours.");
    }
}
