<?php

namespace App\Modules\VieScolaire\Filament\Resources\IncidentVieScolaireResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\IncidentVieScolaireResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIncident extends CreateRecord
{
    protected static string $resource = IncidentVieScolaireResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['cree_par'] = auth()->id();

        return $data;
    }
}
