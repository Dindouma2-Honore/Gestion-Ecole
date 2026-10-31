<?php

namespace App\Modules\Pedagogie\Filament\Resources\EvaluationResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\EvaluationResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEvaluation extends EditRecord
{
    protected static string $resource = EvaluationResource::class;

    protected function beforeSave(): void
    {
        if ($this->record->verrouillee) {
            Notification::make()
                ->title('Modification impossible')
                ->body('Cette évaluation est verrouillée.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
