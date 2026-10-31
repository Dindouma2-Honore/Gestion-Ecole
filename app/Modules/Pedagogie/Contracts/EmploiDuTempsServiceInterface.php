<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Support\Collection;

interface EmploiDuTempsServiceInterface
{
    // Peut lever ConflitEmploiDuTempsException en cas de chevauchement, d'enseignant inactif ou de volume d'offre dépassé.
    public function planifierCours(array $donnees): object;

    // Peut lever ConflitEmploiDuTempsException en cas de chevauchement, d'enseignant inactif ou de volume d'offre dépassé.
    public function modifierCours(int $id, array $donnees): object;

    public function detecterConflits(array $donnees, ?int $excludeId = null): array;

    /** Génère et enregistre atomiquement une proposition sans dépasser les offres. */
    public function genererProposition(int $classeId, int $anneeScolaireId): array;

    /** @return array<int, string> */
    public function verifierEmploiDuTemps(int $classeId, int $anneeScolaireId): array;

    public function validerEmploiDuTemps(int $classeId, int $anneeScolaireId): void;

    public function publierEmploiDuTemps(int $classeId, int $anneeScolaireId): void;

    public function getStatutEmploiDuTemps(int $classeId, int $anneeScolaireId): string;

    public function getCoursDeLaSemaine(int $classeId, int $anneeScolaireId): Collection;

    public function getEmploiDuTempsEnseignant(int $enseignantId): Collection;

    /**
     * Suggère l'enseignant approprié pour un cours en fonction du cycle et des affectations.
     */
    public function getEnseignantSuggere(int $classeId, ?int $matiereId, int $anneeScolaireId): ?int;
}
