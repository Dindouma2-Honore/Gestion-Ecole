<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class RepartitionTranchesInvalideException extends DomainException
{
    public function __construct(float $attendu, float $obtenu)
    {
        parent::__construct(sprintf('La somme des tranches (%.2f) doit être égale au montant total (%.2f).', $obtenu, $attendu));
    }
}
