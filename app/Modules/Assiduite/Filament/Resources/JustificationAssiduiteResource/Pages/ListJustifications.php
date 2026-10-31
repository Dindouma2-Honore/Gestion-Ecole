<?php

namespace App\Modules\Assiduite\Filament\Resources\JustificationAssiduiteResource\Pages;

use App\Modules\Assiduite\Filament\Resources\JustificationAssiduiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJustifications extends ListRecords
{
    protected static string $resource = JustificationAssiduiteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
