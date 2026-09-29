<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use RuntimeException;

class DepenseServiceIndisponibleException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La consommation budgétaire sera disponible lorsque E44 implémentera DepenseServiceContract.');
    }
}
