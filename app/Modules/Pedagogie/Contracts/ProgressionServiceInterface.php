<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

interface ProgressionServiceInterface
{
    // Peut lever SeanceNonDispenseeException (voir Exceptions/) si la séance n'est pas dispensée.
    public function saisirProgression(int $seanceId, ?int $chapitreId, string $contenu, ?string $devoirs = null): object;

    /**
     * Déclare un chapitre du programme comme déjà couvert par l'enseignant,
     * indépendamment d'une séance précise (rattrapage, déclaration groupée en
     * début d'année, etc.). Idempotent : si le chapitre est déjà déclaré
     * couvert, met simplement à jour le commentaire plutôt que de dupliquer.
     */
    public function declarerChapitreCouvert(int $enseignantId, int $chapitreId, ?string $commentaire = null): object;

    public function getPourcentageAvancement(int $matiereId, int $classeId, int $anneeScolaireId): float;

    public function getRetardPedagogique(int $matiereId, int $classeId): array;
}
