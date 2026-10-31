<?php

namespace App\Modules\VieScolaire\Filament\Resources\IncidentVieScolaireResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\IncidentVieScolaireResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIncidents extends ListRecords
{
    protected static string $resource = IncidentVieScolaireResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
