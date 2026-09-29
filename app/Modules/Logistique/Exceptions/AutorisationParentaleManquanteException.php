<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class AutorisationParentaleManquanteException extends Exception
{
    public function __construct(int $participantId)
    {
        parent::__construct("La participation #{$participantId} nécessite une autorisation parentale non encore reçue.");
    }
}
