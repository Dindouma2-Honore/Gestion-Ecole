<?php

namespace App\Modules\RH\Filament\Resources\PersonnelPrimeResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\PersonnelPrimeResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListPersonnelPrimes extends ListRecords
{
    protected static string $resource = PersonnelPrimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create()
                ->label('Attribuer une prime')
                ->modal(true)
                ->modalHeading('Attribuer une prime')
                ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                ->createAnother(false),
        ];
    }
}
