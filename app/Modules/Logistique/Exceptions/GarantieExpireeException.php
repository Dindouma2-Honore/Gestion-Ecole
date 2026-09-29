<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class GarantieExpireeException extends Exception
{
    public function __construct(int $equipementId)
    {
        parent::__construct("La garantie de l'équipement #{$equipementId} est expirée — avertissement, la panne peut tout de même être déclarée.");
    }
}
