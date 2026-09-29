<?php

namespace App\Modules\Socle\Filament\Resources\SeuilValidationResource\Pages;

use App\Modules\Socle\Filament\Resources\SeuilValidationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeuilsValidation extends ListRecords
{
    protected static string $resource = SeuilValidationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
