<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use RuntimeException;

class ValidationVersementInterditeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Seule une utilisatrice ayant le rôle Comptable peut confirmer ce versement.');
    }
}
