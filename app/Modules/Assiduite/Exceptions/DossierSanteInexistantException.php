<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Exceptions;

use Exception;

class DossierSanteInexistantException extends Exception
{
    public function __construct(int $eleveId)
    {
        parent::__construct("Aucun dossier santé enregistré pour l'élève #{$eleveId}.");
    }
}
