<?php

namespace App\Modules\RH\Filament\Resources\ContratResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\ContratResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListContrats extends ListRecords
{
    protected static string $resource = ContratResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create()
                ->label('Nouveau contrat / renouvellement')
                ->modal(true)
                ->modalHeading('Nouveau contrat / renouvellement')
                ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                ->createAnother(false),
        ];
    }
}
