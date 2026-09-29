<?php

namespace App\Modules\Assiduite\Filament\Resources\SortieEleveResource\Pages;

use App\Modules\Assiduite\Filament\Resources\SortieEleveResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSortieEleve extends EditRecord
{
    protected static string $resource = SortieEleveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
