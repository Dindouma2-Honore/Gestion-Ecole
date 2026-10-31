<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Pages;

use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Exceptions\ConflitEmploiDuTempsException;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use DomainException;

class CreateEmploiDuTemps extends CreateRecord
{
    protected static string $resource = EmploiDuTempsResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Enregistrer brouillon');
    }

    /** La détection de conflit (ConflitEmploiDuTempsException) reste dans le Service. */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $cours = app(EmploiDuTempsServiceInterface::class)->planifierCours($data);

            return EmploiDuTemps::findOrFail($cours->id);
        } catch (ConflitEmploiDuTempsException $e) {
            Notification::make()
                ->title('Conflit d\'emploi du temps détecté')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        } catch (DomainException $e) {
            Notification::make()->title('Modification refusée')->body($e->getMessage())->danger()->send();
            $this->halt();
        }
    }
}
