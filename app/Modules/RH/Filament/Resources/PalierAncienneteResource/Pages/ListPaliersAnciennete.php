<?php

namespace App\Modules\RH\Filament\Resources\PalierAncienneteResource\Pages;

use App\Modules\RH\Filament\Resources\PalierAncienneteResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListPaliersAnciennete extends ListRecords
{
    protected static string $resource = PalierAncienneteResource::class;

    protected function getHeaderActions(): array
    {
        return [RhActions::create()->label('Nouveau palier')];
    }
}
