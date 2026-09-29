<?php

namespace App\Modules\Assiduite\Filament\Resources\ReclamationResource\Pages;

use App\Modules\Assiduite\Filament\Resources\ReclamationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReclamation extends EditRecord
{
    protected static string $resource = ReclamationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
