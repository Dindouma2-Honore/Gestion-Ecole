<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Exceptions;

use DomainException;

class SujetExamenNonValideException extends DomainException
{
    public static function pourEvaluation(int $evaluationId, string $statut): self
    {
        return new self(
            "Impossible de saisir une note pour l'évaluation #{$evaluationId} : ".
            "le sujet d'examen n'est pas encore validé par le Directeur (statut actuel : {$statut})."
        );
    }
}
