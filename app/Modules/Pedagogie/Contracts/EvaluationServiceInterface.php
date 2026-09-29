<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Http\UploadedFile;

interface EvaluationServiceInterface
{
    /**
     * L'enseignant propose un sujet d'examen (avec, optionnellement, le
     * fichier du sujet). L'évaluation est créée au statut "soumis" et ne
     * pourra recevoir de notes qu'une fois validée par le Directeur.
     */
    public function proposerSujet(
        int $enseignantId,
        int $matiereId,
        int $classeId,
        string $titre,
        string $dateEvaluation,
        float $bareme,
        ?UploadedFile $sujet = null
    ): object;

    public function validerSujet(int $evaluationId, int $validateurId): void;

    public function rejeterSujet(int $evaluationId, string $motif): void;
}
