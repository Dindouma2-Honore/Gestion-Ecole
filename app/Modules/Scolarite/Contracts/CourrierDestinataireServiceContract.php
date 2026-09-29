<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

interface CourrierDestinataireServiceContract
{
    /** Retourne des tableaux de contacts, jamais des modèles internes. */
    public function resoudreParents(string $cible, array $criteres = []): array;

    public function optionsParents(): array;

    public function optionsClasses(): array;
}
