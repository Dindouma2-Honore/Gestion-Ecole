<?php

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class FactureIntrouvableException extends Exception
{
    public static function pourId(int $factureId): self
    {
        return new self("La facture #{$factureId} est introuvable.");
    }
}
