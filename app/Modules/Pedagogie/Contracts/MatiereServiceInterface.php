<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

interface MatiereServiceInterface
{
    public function existe(int $matiereId): bool;

    /**
     * @return array{id: int, nom: string, code: string, coefficient: float}
     */
    public function getMatiere(int $matiereId): array;

    /** @return array<int, array{id: int, nom: string, code: string, coefficient: float}> */
    public function toutesActives(): array;

    // Le code peut être omis pour utiliser le générateur automatique du modèle.
    public function creer(string $nom, ?string $code, float $coefficient, ?string $description = null, string $type = 'matiere'): array;
}
