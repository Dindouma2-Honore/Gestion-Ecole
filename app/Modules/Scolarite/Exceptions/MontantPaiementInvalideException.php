<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class MontantPaiementInvalideException extends Exception
{
    public function __construct(float $montant)
    {
        parent::__construct(
            "Le montant du versement ({$montant}) doit être strictement supérieur à zéro."
        );
    }
}
