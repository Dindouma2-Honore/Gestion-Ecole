<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use DomainException;

class VersementSuperieurAuDuException extends DomainException
{
    public function __construct(float $verse, float $reste)
    {
        parent::__construct("Le versement de {$verse} FCFA dépasse le reste dû de {$reste} FCFA.");
    }
}
