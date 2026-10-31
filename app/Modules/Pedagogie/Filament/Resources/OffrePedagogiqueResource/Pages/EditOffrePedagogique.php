<?php

namespace App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOffrePedagogique extends EditRecord
{
    protected static string $resource = OffrePedagogiqueResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return OffrePedagogiqueResource::recordData($data, $this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return OffrePedagogiqueResource::enregistrer($data, $record);
    }
}
