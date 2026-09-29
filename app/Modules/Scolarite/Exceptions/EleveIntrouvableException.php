<?php

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class EleveIntrouvableException extends Exception
{
    public static function pourId(int $eleveId): self
    {
        return new self("L'élève #{$eleveId} est introuvable.");
    }
}
