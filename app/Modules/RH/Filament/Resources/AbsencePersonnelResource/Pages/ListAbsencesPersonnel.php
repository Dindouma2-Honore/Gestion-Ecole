<?php

namespace App\Modules\RH\Filament\Resources\AbsencePersonnelResource\Pages;

use App\Modules\RH\Filament\Resources\AbsencePersonnelResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListAbsencesPersonnel extends ListRecords
{
    protected static string $resource = AbsencePersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [RhActions::create()];
    }
}
