<?php

namespace App\Modules\RH\Filament\Resources\PointageResource\Pages;

use App\Modules\RH\Filament\Resources\PointageResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListPointages extends ListRecords
{
    protected static string $resource = PointageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RhActions::create(),
        ];
    }
}
