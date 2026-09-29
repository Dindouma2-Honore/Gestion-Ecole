<?php

namespace App\Modules\RH\Filament\Resources\PersonnelPrimeResource\Pages;

use App\Modules\RH\Filament\Resources\PersonnelPrimeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersonnelPrimes extends ListRecords
{
    protected static string $resource = PersonnelPrimeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
