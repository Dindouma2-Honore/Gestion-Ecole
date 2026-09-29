<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use RuntimeException;

class FraisInscriptionNonConfiguresException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Les frais obligatoires d'inscription ne sont pas configurés pour ce niveau et cette année.");
    }
}
