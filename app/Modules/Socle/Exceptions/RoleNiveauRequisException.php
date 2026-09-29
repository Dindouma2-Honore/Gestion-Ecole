<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class RoleNiveauRequisException extends Exception
{
    public function __construct(string $role)
    {
        parent::__construct("Le rôle '{$role}' nécessite un niveau_id — impossible de l'assigner sans niveau.");
    }
}
