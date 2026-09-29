<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EvaluationService implements EvaluationServiceInterface
{
    public function __construct(
        private readonly ?DocumentServiceContract $documentService = null,
        private readonly ?AuditServiceContract $auditService = null,
    ) {}

    public function proposerSujet(
        int $enseignantId,
        int $matiereId,
        int $classeId,
        string $titre,
        string $dateEvaluation,
        float $bareme,
        ?UploadedFile $sujet = null
    ): object {
        return DB::transaction(function () use ($enseignantId, $matiereId, $classeId, $titre, $dateEvaluation, $bareme, $sujet): Evaluation {
            $evaluation = Evaluation::create([
                'titre' => $titre,
                'matiere_id' => $matiereId,
                'classe_id' => $classeId,
                'date_evaluation' => $dateEvaluation,
                'bareme' => $bareme,
                'created_by' => $enseignantId,
                'statut' => 'soumis',
            ]);

            if ($sujet !== null && $this->documentService !== null) {
                $doc = $this->documentService->attacher($evaluation, $sujet, 'sujet_examen');
                if (isset($doc->id)) {
                    $evaluation->update(['document_sujet_id' => $doc->id]);
                }
            }

            $this->notifierDirecteurs($evaluation);

            return $evaluation;
        });
    }

    public function validerSujet(int $evaluationId, int $validateurId): void
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        $evaluation->update([
            'statut' => 'valide',
            'valide_par' => $validateurId,
            'motif_rejet' => null,
        ]);

        if ($this->auditService !== null) {
            $this->auditService->enregistrer($evaluation, "Validation du sujet d'examen #{$evaluationId}");
        }
    }

    public function rejeterSujet(int $evaluationId, string $motif): void
    {
        if (trim($motif) === '') {
            throw new InvalidArgumentException('Un motif de rejet est obligatoire.');
        }

        $evaluation = Evaluation::findOrFail($evaluationId);
        $evaluation->update([
            'statut' => 'rejete',
            'motif_rejet' => $motif,
        ]);

        if ($this->auditService !== null) {
            $this->auditService->enregistrerAvecMotif($evaluation, "Rejet du sujet d'examen #{$evaluationId}", $motif);
        }

        if ($evaluation->created_by) {
            $enseignantUser = User::find($evaluation->created_by);
            if ($enseignantUser !== null && class_exists(\Filament\Notifications\Notification::class)) {
                \Filament\Notifications\Notification::make()
                    ->title("Sujet d'examen rejeté")
                    ->body("Motif du rejet : {$motif}")
                    ->danger()
                    ->sendToDatabase($enseignantUser);
            }
        }
    }

    private function notifierDirecteurs(Evaluation $evaluation): void
    {
        if (! class_exists(\Filament\Notifications\Notification::class)) {
            return;
        }

        $directeurs = User::role(['Directeur', 'Fondateur'])->get();

        foreach ($directeurs as $directeur) {
            \Filament\Notifications\Notification::make()
                ->title("Nouveau sujet d'examen à valider")
                ->body("Un sujet d'examen (#{$evaluation->id} - {$evaluation->titre}) a été soumis pour la classe #{$evaluation->classe_id}.")
                ->info()
                ->sendToDatabase($directeur);
        }
    }
}
