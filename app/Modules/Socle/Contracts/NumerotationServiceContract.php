<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

interface NumerotationServiceContract
{
    /** Génère atomiquement le prochain numéro configuré pour un type de document. */
    public function next(string $documentType, array $variables = []): string;
}
