<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

interface ClasseServiceInterface
{
    public function existe(int $classeId): bool;

    public function getNomClasse(int $classeId): string;

    public function getNiveauId(int $classeId): int;

    public function getEffectif(int $classeId): int;

    public function getPlacesRestantes(int $classeId): int;

    /**
     * Retourne toutes les classes d'une année scolaire — utilisé pour
     * peupler les sélecteurs de classe dans les autres modules.
     *
     * @return array<int, array{id: int, nom: string}>
     */
    public function getToutesLesClasses(int $anneeScolaireId): array;
}
