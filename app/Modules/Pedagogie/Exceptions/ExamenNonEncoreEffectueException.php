<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Exceptions;

use DomainException;

class ExamenNonEncoreEffectueException extends DomainException
{
    public static function pourEvaluation(int $evaluationId, string $dateEvaluation): self
    {
        return new self(
            "Impossible de saisir une note pour l'évaluation #{$evaluationId} : ".
            "la date de l'examen ({$dateEvaluation}) n'est pas encore passée."
        );
    }
}
