<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Exceptions;

use Exception;

class PersonneNonAutoriseeException extends Exception
{
    public function __construct(int $parentId, int $eleveId)
    {
        parent::__construct(
            "Le parent #{$parentId} n'est pas autorisé à récupérer l'élève #{$eleveId} — vérifiez la table des autorisations (module Scolarité)."
        );
    }
}
