<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class BulletinDejaValideException extends Exception
{
    public function __construct(int $bulletinId)
    {
        parent::__construct("Le bulletin #{$bulletinId} est déjà validé — il ne peut plus être modifié ni recalculé.");
    }
}
