<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class TypeFichierNonAutoriseException extends Exception
{
    public function __construct(string $extension, array $typesAutorises)
    {
        parent::__construct("Le type de fichier '.{$extension}' n'est pas autorisé. Types acceptés : ".implode(', ', $typesAutorises));
    }
}
