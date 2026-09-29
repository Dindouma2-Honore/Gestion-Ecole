<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use Exception;

class SessionCaisseDejaOuverteException extends Exception
{
    public function __construct(string $date)
    {
        parent::__construct("Une session de caisse est déjà ouverte pour la date {$date}.");
    }
}
