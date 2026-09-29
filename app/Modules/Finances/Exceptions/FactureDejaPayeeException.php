<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use RuntimeException;

class FactureDejaPayeeException extends RuntimeException
{
    public function __construct(int $factureId)
    {
        parent::__construct("La facture provisoire #{$factureId} a déjà été confirmée.");
    }
}
