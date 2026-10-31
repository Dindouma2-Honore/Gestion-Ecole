<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Exceptions;

use DomainException;

class EnseignantPrincipalDejaAffecteException extends DomainException
{
    public function __construct(string $message = 'Un enseignant principal est déjà affecté à cette classe pour l’année scolaire en cours.')
    {
        parent::__construct($message);
    }
}
