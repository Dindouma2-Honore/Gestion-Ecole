<?php

namespace App\Modules\RH\Filament\Resources\TypeCongeResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\TypeCongeResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListTypeConges extends ListRecords
{
    protected static string $resource = TypeCongeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create()
                ->label('Nouveau type de congé')
                ->modal(true)
                ->modalHeading('Nouveau type de congé')
                ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                ->createAnother(false),
        ];
    }
}
