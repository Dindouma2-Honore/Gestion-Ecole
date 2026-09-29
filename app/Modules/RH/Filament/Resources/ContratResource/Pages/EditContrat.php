<?php

namespace App\Modules\RH\Filament\Resources\ContratResource\Pages;

use App\Modules\RH\Filament\Resources\ContratResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContrat extends EditRecord
{
    protected static string $resource = ContratResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
