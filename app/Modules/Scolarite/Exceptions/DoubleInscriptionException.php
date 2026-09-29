<?php

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class DoubleInscriptionException extends Exception
{
    public function __construct(int $eleveId, int $anneeScolaireId)
    {
        parent::__construct(
            "L'élève #{$eleveId} possède déjà une inscription active pour l'année scolaire #{$anneeScolaireId}."
        );
    }
}
