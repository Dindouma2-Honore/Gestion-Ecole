<?php

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class MatiereIntrouvableException extends Exception
{
    public static function pourId(int $matiereId): self
    {
        return new self("La matière #{$matiereId} est introuvable.");
    }
}
