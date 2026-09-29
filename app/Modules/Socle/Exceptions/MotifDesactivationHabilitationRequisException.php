<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use RuntimeException;

class MotifDesactivationHabilitationRequisException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Un motif est obligatoire pour désactiver l'accès à une fonctionnalité.");
    }
}
