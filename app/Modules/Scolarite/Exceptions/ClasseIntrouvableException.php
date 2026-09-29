<?php

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class ClasseIntrouvableException extends Exception
{
    public static function pourId(int $classeId): self
    {
        return new self("La classe #{$classeId} est introuvable.");
    }
}
