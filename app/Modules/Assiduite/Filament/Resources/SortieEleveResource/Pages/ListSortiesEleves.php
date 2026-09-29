<?php

namespace App\Modules\Assiduite\Filament\Resources\SortieEleveResource\Pages;

use App\Modules\Assiduite\Filament\Resources\SortieEleveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSortiesEleves extends ListRecords
{
    protected static string $resource = SortieEleveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
