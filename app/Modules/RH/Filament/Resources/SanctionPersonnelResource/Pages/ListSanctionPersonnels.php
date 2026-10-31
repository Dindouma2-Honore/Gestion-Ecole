<?php

namespace App\Modules\RH\Filament\Resources\SanctionPersonnelResource\Pages;

use App\Modules\RH\Filament\Resources\SanctionPersonnelResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListSanctionPersonnels extends ListRecords
{
    protected static string $resource = SanctionPersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create(),
        ];
    }
}
