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
}
