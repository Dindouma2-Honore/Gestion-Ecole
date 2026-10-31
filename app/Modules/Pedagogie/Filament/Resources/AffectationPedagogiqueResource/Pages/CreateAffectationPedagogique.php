<?php

namespace App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAffectationPedagogique extends CreateRecord
{
    protected static string $resource = AffectationPedagogiqueResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return AffectationPedagogiqueResource::enregistrer($data);
    }
}
