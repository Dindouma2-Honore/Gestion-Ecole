<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class AucuneAnneeActiveException extends Exception
{
    public function __construct()
    {
        parent::__construct("Aucune année scolaire n'est actuellement active. Contactez l'administrateur pour en activer une.");
    }
}
