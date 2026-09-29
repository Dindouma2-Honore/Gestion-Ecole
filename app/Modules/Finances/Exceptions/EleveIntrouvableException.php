<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use Exception;

class EleveIntrouvableException extends Exception
{
    public function __construct(int $eleveId)
    {
        parent::__construct("L'élève #{$eleveId} est introuvable.");
    }
}
