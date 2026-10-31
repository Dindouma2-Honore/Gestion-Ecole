<?php

namespace App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMatiere extends EditRecord
{
    protected static string $resource = MatiereResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return MatiereResource::recordData($data, $this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return MatiereResource::enregistrer($data, $record);
    }
}
