<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class MotifAnnulationRequisException extends Exception
{
    public function __construct()
    {
        parent::__construct('Un motif est obligatoire pour toute annulation.');
    }
}
