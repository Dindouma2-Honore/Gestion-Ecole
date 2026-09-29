<?php

namespace App\Modules\Assiduite\Filament\Resources\VisiteurResource\Pages;

use App\Modules\Assiduite\Filament\Resources\VisiteurResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVisiteur extends EditRecord
{
    protected static string $resource = VisiteurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
