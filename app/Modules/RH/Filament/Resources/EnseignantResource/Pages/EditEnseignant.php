<?php

namespace App\Modules\RH\Filament\Resources\EnseignantResource\Pages;

use App\Modules\RH\Filament\Resources\EnseignantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEnseignant extends EditRecord
{
    protected static string $resource = EnseignantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
