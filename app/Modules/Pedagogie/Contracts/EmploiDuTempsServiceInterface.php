<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Support\Collection;

interface EmploiDuTempsServiceInterface
{
    // Peut lever ConflitEmploiDuTempsException (voir Exceptions/) si l'enseignant, la salle ou la classe sont déjà occupés sur ce créneau.
    public function planifierCours(array $donnees): object;

    // Peut lever ConflitEmploiDuTempsException (voir Exceptions/) si l'enseignant, la salle ou la classe sont déjà occupés sur ce créneau.
    public function modifierCours(int $id, array $donnees): object;

    public function detecterConflits(array $donnees, ?int $excludeId = null): array;

    public function getCoursDeLaSemaine(int $classeId, int $anneeScolaireId): Collection;

    public function getEmploiDuTempsEnseignant(int $enseignantId): Collection;
}
