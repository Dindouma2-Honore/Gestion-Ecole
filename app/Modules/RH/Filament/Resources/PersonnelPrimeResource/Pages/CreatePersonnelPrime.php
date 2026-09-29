<?php

namespace App\Modules\RH\Filament\Resources\PersonnelPrimeResource\Pages;

use App\Modules\RH\Filament\Resources\PersonnelPrimeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonnelPrime extends CreateRecord
{
    protected static string $resource = PersonnelPrimeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
