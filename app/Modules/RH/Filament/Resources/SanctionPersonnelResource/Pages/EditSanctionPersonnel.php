<?php

namespace App\Modules\RH\Filament\Resources\SanctionPersonnelResource\Pages;

use App\Modules\RH\Filament\Resources\SanctionPersonnelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSanctionPersonnel extends EditRecord
{
    protected static string $resource = SanctionPersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
