<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\EvaluationResource\Pages;

use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\EvaluationResource;
use App\Modules\Pedagogie\Models\Evaluation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateEvaluation extends CreateRecord
{
    protected static string $resource = EvaluationResource::class;

    /**
     * Passe par EvaluationServiceInterface::proposerSujet() : l'enseignant
     * propose un sujet d'examen, qui est créé au statut "soumis" en attente
     * de validation par le Directeur (voir actions "Valider"/"Rejeter" de la
     * table). Les notes ne pourront être saisies qu'une fois le sujet validé.
     */
    protected function handleRecordCreation(array $data): Evaluation
    {
        $sujet = $data['sujet'] ?? null;

        if ($sujet instanceof TemporaryUploadedFile) {
            $sujet = new UploadedFile(
                $sujet->getRealPath(),
                $sujet->getClientOriginalName(),
                $sujet->getMimeType(),
                null,
                true
            );
        }

        return app(EvaluationServiceInterface::class)->proposerSujet(
            enseignantId: Auth::id() ?? 1,
            matiereId: (int) $data['matiere_id'],
            classeId: (int) $data['classe_id'],
            titre: $data['titre'],
            dateEvaluation: $data['date_evaluation'],
            bareme: (float) $data['bareme'],
            sujet: $sujet instanceof UploadedFile ? $sujet : null,
        );
    }
}
