<?php

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class CodeMatiereDejaUtiliseException extends Exception
{
    public static function pourCode(string $code): self
    {
        return new self("Le code matière '{$code}' est déjà utilisé par une autre matière.");
    }
}
