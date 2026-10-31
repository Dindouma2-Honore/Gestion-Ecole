<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class CategorieMaterielCodeDejaUtiliseException extends Exception
{
    public function __construct(string $code)
    {
        parent::__construct("Le code '{$code}' est déjà utilisé par une catégorie de matériel.");
    }
}
