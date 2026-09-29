<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class EcritureAnneeNonAutoriseeException extends Exception
{
    public function __construct(int $anneeScolaireId)
    {
        parent::__construct(
            "Écriture non autorisée sur l'année scolaire #{$anneeScolaireId} (clôturée, archivée, ou hors des droits de l'utilisateur)."
        );
    }
}