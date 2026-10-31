<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Pages;

use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Exceptions\ConflitEmploiDuTempsException;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use DomainException;

class EditEmploiDuTemps extends EditRecord
{
    protected static string $resource = EmploiDuTempsResource::class;

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Enregistrer les modifications');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(EmploiDuTempsServiceInterface::class)->modifierCours($record->id, $data);
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
