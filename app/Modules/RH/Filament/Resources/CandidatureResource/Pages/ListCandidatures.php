<?php

namespace App\Modules\RH\Filament\Resources\CandidatureResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\CandidatureResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListCandidatures extends ListRecords
{
    protected static string $resource = CandidatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create()
                ->label('Nouvelle candidature')
                ->modal(true)
                ->modalHeading('Nouvelle candidature')
                ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                ->createAnother(false),
        ];
    }
}
