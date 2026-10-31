<?php

namespace App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOffrePedagogique extends CreateRecord
{
    protected static string $resource = OffrePedagogiqueResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return OffrePedagogiqueResource::enregistrer($data);
    }
}
