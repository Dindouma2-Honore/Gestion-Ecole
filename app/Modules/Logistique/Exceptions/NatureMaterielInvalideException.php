<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class NatureMaterielInvalideException extends Exception
{
    public function __construct(string $nature)
    {
        parent::__construct("La nature '{$nature}' est invalide : DURABLE ou CONSOMMABLE est attendu.");
    }
}
