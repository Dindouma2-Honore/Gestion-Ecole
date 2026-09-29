<?php

namespace App\Modules\RH\Filament\Resources\CandidatureResource\Pages;

use App\Modules\RH\Filament\Resources\CandidatureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCandidature extends EditRecord
{
    protected static string $resource = CandidatureResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
