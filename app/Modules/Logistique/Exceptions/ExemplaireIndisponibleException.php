<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class ExemplaireIndisponibleException extends Exception
{
    public function __construct(int $exemplaireId)
    {
        parent::__construct("L'exemplaire #{$exemplaireId} n'est pas disponible actuellement.");
    }
}
