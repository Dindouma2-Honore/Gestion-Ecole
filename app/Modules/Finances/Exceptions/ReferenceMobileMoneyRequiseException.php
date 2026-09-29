<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class ReferenceMobileMoneyRequiseException extends DomainException
{
    public function __construct()
    {
        parent::__construct('La référence de transaction est obligatoire pour un paiement Mobile Money.');
    }
}
