<?php

namespace App\Modules\Assiduite\Filament\Resources\DossierSanteResource\Pages;

use App\Modules\Assiduite\Filament\Resources\DossierSanteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDossierSante extends EditRecord
{
    protected static string $resource = DossierSanteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
