<?php

namespace App\Modules\Pedagogie\Filament\Resources\NoteResource\Pages;

use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Exceptions\ExamenNonEncoreEffectueException;
use App\Modules\Pedagogie\Exceptions\NoteInvalideException;
use App\Modules\Pedagogie\Exceptions\NoteModificationVerrouilleeException;
use App\Modules\Pedagogie\Exceptions\SujetExamenNonValideException;
use App\Modules\Pedagogie\Filament\Resources\NoteResource;
use App\Modules\Pedagogie\Models\Note;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $copie = $data['copie'] ?? null;

        if ($copie instanceof TemporaryUploadedFile) {
            $copie = new UploadedFile($copie->getRealPath(), $copie->getClientOriginalName(), $copie->getMimeType(), null, true);
        }

        try {
            $service = app(NoteServiceInterface::class);
            $note = ($data['absent'] ?? false) ? $service->enregistrerAbsence(
                (int) $data['evaluation_id'],
                (int) $data['eleve_id'],
            ) : $service->enregistrer(
                (int) $data['evaluation_id'],
                (int) $data['eleve_id'],
                (float) ($data['valeur'] ?? 0),
                $copie instanceof UploadedFile ? $copie : null,
            );
        } catch (
            SujetExamenNonValideException
            |ExamenNonEncoreEffectueException
            |NoteInvalideException
            |NoteModificationVerrouilleeException $e
        ) {
            Notification::make()
                ->title('Saisie impossible')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }

        return Note::findOrFail($note->id);
    }
}
