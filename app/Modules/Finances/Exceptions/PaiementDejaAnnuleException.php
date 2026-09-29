<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class PaiementDejaAnnuleException extends DomainException
{
    public function __construct(int $paiementId)
    {
        parent::__construct("Le paiement #{$paiementId} est déjà annulé.");
    }
}
