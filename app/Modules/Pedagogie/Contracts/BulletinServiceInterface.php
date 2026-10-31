<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Support\Collection;

interface BulletinServiceInterface
{
    /**
     * Génère (ou régénère) le bulletin d'un élève pour une classe/année, en
     * calculant la moyenne par matière (pondérée par le coefficient), la
     * moyenne générale et le rang de l'élève dans sa classe. Ne prend en
     * compte que les évaluations dont le sujet a été validé.
     *
     * $periodeId : optionnel, pour un bulletin par trimestre/semestre. Si
     * omis, le bulletin couvre toute l'année scolaire.
     */
    public function genererPourEleve(int $eleveId, int $classeId, int $anneeScolaireId, ?int $periodeId = null): object;

    /** Génère le bulletin de chaque élève de la classe. */
    public function genererPourClasse(int $classeId, int $anneeScolaireId, ?int $periodeId = null): Collection;

    public function changerStatut(int $bulletinId, string $nouveauStatut): object;

    /**
     * Enregistre la décision officielle du conseil de classe pour un élève (admis, redouble, etc.).
     */
    public function enregistrerDecisionConseil(
        int $eleveId,
        int $classeId,
        int $anneeScolaireId,
        string $decision,
        ?string $motif = null,
        ?int $validePar = null
    ): object;

    /**
     * Récupère la décision pédagogique du conseil pour un élève.
     */
    public function getDecisionConseil(int $eleveId, int $anneeScolaireId): ?object;

    /**
     * Récupère toutes les décisions pédagogiques pour une classe et une année scolaire.
     */
    public function getDecisionsClasse(int $classeId, int $anneeScolaireId): Collection;
}
