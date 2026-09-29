<?php

namespace App\Modules\Pedagogie\Filament\Resources\DisciplineEleveResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\DisciplineEleveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDisciplineEleves extends ListRecords
{
    protected static string $resource = DisciplineEleveResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
