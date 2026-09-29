<?php

namespace App\Modules\Socle\Filament\Resources\JourFerieResource\Pages;

use App\Modules\Socle\Filament\Resources\JourFerieResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJourFerie extends CreateRecord
{
    protected static string $resource = JourFerieResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['date_debut'] = $data['date'];
        $data['date_fin'] = $data['date'];

        return $data;
    }
}
