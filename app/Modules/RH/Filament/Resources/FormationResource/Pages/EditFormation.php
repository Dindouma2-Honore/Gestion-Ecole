<?php

namespace App\Modules\RH\Filament\Resources\FormationResource\Pages;

use App\Modules\RH\Filament\Resources\FormationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormation extends EditRecord
{
    protected static string $resource = FormationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
