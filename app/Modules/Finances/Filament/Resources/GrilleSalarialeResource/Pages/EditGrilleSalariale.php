<?php

namespace App\Modules\Finances\Filament\Resources\GrilleSalarialeResource\Pages;

use App\Modules\Finances\Filament\Resources\GrilleSalarialeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrilleSalariale extends EditRecord
{
    protected static string $resource = GrilleSalarialeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
