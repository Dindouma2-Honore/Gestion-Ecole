<?php

namespace App\Modules\RH\Filament\Resources\PrimeResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\PrimeResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListPrimes extends ListRecords
{
    protected static string $resource = PrimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create()
                ->label('Créer une prime')
                ->modal(true)
                ->modalHeading('Créer une prime')
                ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                ->createAnother(false),
        ];
    }
}
