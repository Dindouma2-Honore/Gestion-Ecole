<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class ModePaiementInvalideException extends DomainException
{
    public function __construct(string $mode)
    {
        parent::__construct("Le mode de paiement '{$mode}' est invalide.");
    }
}
