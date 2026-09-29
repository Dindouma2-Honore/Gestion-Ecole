<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class MontantPaiementInvalideException extends DomainException
{
    public function __construct(float $montant)
    {
        parent::__construct("Le montant du paiement ({$montant}) doit être strictement positif.");
    }
}
