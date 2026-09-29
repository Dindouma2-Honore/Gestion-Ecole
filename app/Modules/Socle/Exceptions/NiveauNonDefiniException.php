<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class NiveauNonDefiniException extends Exception
{
    public function __construct(int $userId)
    {
        parent::__construct("L'utilisateur #{$userId} a un rôle nécessitant un niveau, mais aucun niveau_id n'est renseigné.");
    }
}
