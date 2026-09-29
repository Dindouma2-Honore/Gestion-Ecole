<?php

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class ConflitEmploiDuTempsException extends Exception
{
    public function __construct(private readonly array $conflits)
    {
        parent::__construct(
            'Conflit(s) détecté(s) dans l\'emploi du temps : '.implode(', ', $conflits)
        );
    }

    public function getConflits(): array
    {
        return $this->conflits;
    }
}
