<?php

namespace App\Modules\RH\Filament\Resources\CongeResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\CongeResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListConges extends ListRecords
{
    protected static string $resource = CongeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create()
                ->label('Créer un congé')
                ->modal(true)
                ->modalHeading('Créer un congé')
                ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                ->createAnother(false),
        ];
    }
}
