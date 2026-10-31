<?php

namespace App\Modules\RH\Filament\Resources\FormationResource\Pages;

use App\Modules\RH\Filament\Resources\FormationResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListFormations extends ListRecords
{
    protected static string $resource = FormationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create(),
        ];
    }
}
