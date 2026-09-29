<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use RuntimeException;

class RoleHabilitationInvalideException extends RuntimeException
{
    public function __construct(string $role)
    {
        parent::__construct("Le rôle '{$role}' ne peut pas être administré dans la matrice des habilitations.");
    }
}
