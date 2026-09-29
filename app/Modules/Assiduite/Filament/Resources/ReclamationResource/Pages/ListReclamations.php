<?php

namespace App\Modules\Assiduite\Filament\Resources\ReclamationResource\Pages;

use App\Modules\Assiduite\Filament\Resources\ReclamationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReclamations extends ListRecords
{
    protected static string $resource = ReclamationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
