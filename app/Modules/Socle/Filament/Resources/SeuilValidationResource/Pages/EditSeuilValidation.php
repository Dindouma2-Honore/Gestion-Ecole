<?php

namespace App\Modules\Socle\Filament\Resources\SeuilValidationResource\Pages;

use App\Modules\Socle\Filament\Resources\SeuilValidationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeuilValidation extends EditRecord
{
    protected static string $resource = SeuilValidationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
