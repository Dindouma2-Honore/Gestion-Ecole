<?php

namespace App\Modules\RH\Filament\Resources\ContratResource\Pages;

use App\Modules\RH\Filament\Resources\ContratResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContrats extends ListRecords
{
    protected static string $resource = ContratResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
