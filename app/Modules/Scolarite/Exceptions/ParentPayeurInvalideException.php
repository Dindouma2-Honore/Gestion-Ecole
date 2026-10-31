<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Exceptions;

use RuntimeException;

class ParentPayeurInvalideException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Aucun parent payeur n'est lié à cet élève. Complétez d'abord son dossier d'admission.");
    }
}
