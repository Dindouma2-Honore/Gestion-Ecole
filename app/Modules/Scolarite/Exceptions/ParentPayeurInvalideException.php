<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Exceptions;

use RuntimeException;

class ParentPayeurInvalideException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Le parent payeur doit être lié à l'élève et posséder une adresse e-mail valide.");
    }
}
