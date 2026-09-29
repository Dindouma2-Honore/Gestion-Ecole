<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use RuntimeException;

class BalanceDepensesIndisponiblesException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Les sorties seront disponibles lorsque E44 fournira le port de lecture de la balance.');
    }
}
