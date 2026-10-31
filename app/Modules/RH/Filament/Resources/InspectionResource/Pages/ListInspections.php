<?php

namespace App\Modules\RH\Filament\Resources\InspectionResource\Pages;

use App\Modules\RH\Filament\Resources\InspectionResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListInspections extends ListRecords
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create(),
        ];
    }
}
