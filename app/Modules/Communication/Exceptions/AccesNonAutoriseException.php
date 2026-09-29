<?php

declare(strict_types=1);

namespace App\Modules\Communication\Exceptions;

use Exception;

class AccesNonAutoriseException extends Exception
{
    public function __construct(int $parentId, int $eleveId)
    {
        parent::__construct("Le parent #{$parentId} n'est pas autorisé à consulter les données de l'élève #{$eleveId}.");
    }
}
