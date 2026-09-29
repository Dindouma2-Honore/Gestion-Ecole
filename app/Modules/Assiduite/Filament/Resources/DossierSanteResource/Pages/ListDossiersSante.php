<?php

namespace App\Modules\Assiduite\Filament\Resources\DossierSanteResource\Pages;

use App\Modules\Assiduite\Filament\Resources\DossierSanteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDossiersSante extends ListRecords
{
    protected static string $resource = DossierSanteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
