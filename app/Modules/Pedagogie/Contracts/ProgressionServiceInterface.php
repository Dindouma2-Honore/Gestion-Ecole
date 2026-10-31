<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

interface ProgressionServiceInterface
{
    // Peut lever SeanceNonDispenseeException (voir Exceptions/) si la séance n'est pas dispensée.
    public function saisirProgression(
        int $seanceId,
        ?int $chapitreId,
        string $contenu,
        ?string $devoirs = null,
        bool $chapitreTermine = false,
        ?int $leconId = null,
        string $statutElement = 'terminee',
        ?int $dureeMinutes = null,
        ?array $devoirData = null,
        ?string $observation = null,
        ?string $dateProgression = null,
    ): object;

    /**
     * Déclare un chapitre du programme comme déjà couvert par l'enseignant,
     * indépendamment d'une séance précise (rattrapage, déclaration groupée en
     * début d'année, etc.). Idempotent : si le chapitre est déjà déclaré
     * couvert, met simplement à jour le commentaire plutôt que de dupliquer.
     */
    public function declarerChapitreCouvert(int $enseignantId, int $chapitreId, ?string $commentaire = null): object;

    public function getPourcentageAvancement(int $matiereId, int $classeId, int $anneeScolaireId): float;

    public function getRetardPedagogique(int $matiereId, int $classeId): array;

    /**
     * Compare l'avancement de toutes les classes d'un même niveau pour une matière.
     * Exemple : 6e A: 78%, 6e B: 71%, etc.
     *
     * @return array{niveau_id: int, matiere_id: int, classes: list<array{classe_id: int, nom: string, progression: float, chapitres_termines: int, total_chapitres: int}>, moyenne_niveau: float}
     */
    public function comparerProgressionNiveau(int $matiereId, int $niveauId, int $anneeScolaireId): array;
}
