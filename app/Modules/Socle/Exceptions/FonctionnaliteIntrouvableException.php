<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use RuntimeException;

class FonctionnaliteIntrouvableException extends RuntimeException
{
    public function __construct(string $code)
    {
        parent::__construct("La fonctionnalité '{$code}' est absente du catalogue des habilitations.");
    }
}
