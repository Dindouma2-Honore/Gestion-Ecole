<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use RuntimeException;

class MotifRetraitPermissionGroupeRequisException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Un motif est obligatoire pour retirer une permission d'un groupe.");
    }
}
