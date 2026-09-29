<?php

namespace App\Modules\RH\Filament\Resources\AbsencePersonnelResource\Pages;

use App\Modules\RH\Filament\Resources\AbsencePersonnelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAbsencePersonnel extends CreateRecord
{
    protected static string $resource = AbsencePersonnelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
