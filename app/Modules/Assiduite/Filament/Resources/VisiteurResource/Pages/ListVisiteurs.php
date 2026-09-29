<?php

namespace App\Modules\Assiduite\Filament\Resources\VisiteurResource\Pages;

use App\Modules\Assiduite\Filament\Resources\VisiteurResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisiteurs extends ListRecords
{
    protected static string $resource = VisiteurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
