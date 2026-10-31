<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;

interface NoteServiceInterface
{
    /**
     * Enregistre la note d'un élève pour une évaluation. Lève :
     * - SujetExamenNonValideException si le sujet n'est pas encore validé par le Directeur.
     * - ExamenNonEncoreEffectueException si la date de l'examen n'est pas encore passée.
     * - NoteInvalideException si la valeur est hors barème.
     * - NoteModificationVerrouilleeException si le délai de modification (7 jours par défaut) est dépassé.
     *
     * $copie : scan/photo de la copie de l'élève (optionnel), attaché via DocumentServiceContract.
     */
    public function enregistrer(int $evaluationId, int $eleveId, float $valeur, ?UploadedFile $copie = null): object;

    public function enregistrerAbsence(int $evaluationId, int $eleveId): object;

    public function peutModifier(int $noteId): bool;

    public function dateVerrouillage(int $noteId): DateTimeInterface;

    public function debloquer(int $noteId, string $motif): void;

    /**
     * Enregistre un lot de notes pour une évaluation donnée (saisie rapide par classe).
     *
     * @param  array<int, array{eleve_id: int, valeur?: ?float, statut_presence?: string, competence_niveau?: ?string, appreciation?: ?string}>  $notesEleves
     * @return array{evaluation_id: int, nb_saisies: int, nb_absents: int, moyenne: ?float}
     */
    public function enregistrerLot(int $evaluationId, array $notesEleves): array;

    /**
     * Verrouille manuellement l'ensemble d'une évaluation.
     */
    public function verrouillerEvaluation(int $evaluationId): void;

    /**
     * Soumet l'ensemble des notes de l'évaluation pour approbation (statut "soumis").
     * Exige que tous les élèves de la liste officielle de la classe soient évalués (pas d'oubli).
     */
    public function soumettreNotes(int $evaluationId, ?int $soumetteurId = null): void;

    /**
     * Approbation par le Fondateur et gel définitif des notes et de l'évaluation.
     */
    public function approuverNotes(int $evaluationId, int $approbateurId): void;
}
