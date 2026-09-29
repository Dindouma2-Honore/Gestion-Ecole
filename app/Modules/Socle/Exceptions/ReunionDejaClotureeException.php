<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class ReunionDejaClotureeException extends Exception
{
    public function __construct(int $reunionId)
    {
        parent::__construct("La réunion #{$reunionId} est déjà clôturée — impossible d'ajouter une décision.");
    }
}
