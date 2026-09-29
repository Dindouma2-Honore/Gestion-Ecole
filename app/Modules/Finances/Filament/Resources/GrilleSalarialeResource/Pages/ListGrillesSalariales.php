<?php

namespace App\Modules\Finances\Filament\Resources\GrilleSalarialeResource\Pages;

use App\Modules\Finances\Filament\Resources\GrilleSalarialeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrillesSalariales extends ListRecords
{
    protected static string $resource = GrilleSalarialeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle ligne')];
    }
}
