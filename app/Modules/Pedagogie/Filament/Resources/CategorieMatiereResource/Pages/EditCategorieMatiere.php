<?php

namespace App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCategorieMatiere extends EditRecord
{
    protected static string $resource = CategorieMatiereResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CategorieMatiereResource::recordData($data, $this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return CategorieMatiereResource::enregistrer($data, $record);
    }
}
