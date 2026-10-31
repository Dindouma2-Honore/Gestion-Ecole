<?php

namespace App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAffectationPedagogique extends EditRecord
{
    protected static string $resource = AffectationPedagogiqueResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return AffectationPedagogiqueResource::recordData($data, $this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return AffectationPedagogiqueResource::enregistrer($data, $record);
    }
}
