<?php

namespace App\Modules\RH\Filament\Resources\TypePrimeResource\Pages;

use App\Modules\RH\Filament\Resources\PersonnelPrimeResource;
use App\Modules\RH\Filament\Resources\TypePrimeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTypePrimes extends ListRecords
{
    protected static string $resource = TypePrimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('attributions')->label('Attributions au personnel')->icon('heroicon-o-user-plus')->url(PersonnelPrimeResource::getUrl()),
        ];
    }
}
