<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class CapaciteVehiculeDepasseeException extends Exception
{
    public function __construct(int $circuitId)
    {
        parent::__construct("Le circuit #{$circuitId} a atteint la capacité maximale du véhicule assigné.");
    }
}
