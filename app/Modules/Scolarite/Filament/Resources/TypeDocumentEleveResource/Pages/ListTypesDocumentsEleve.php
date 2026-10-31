<?php

namespace App\Modules\Scolarite\Filament\Resources\TypeDocumentEleveResource\Pages;

use App\Modules\Scolarite\Filament\Resources\TypeDocumentEleveResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use Filament\Resources\Pages\ListRecords;

class ListTypesDocumentsEleve extends ListRecords
{
    protected static string $resource = TypeDocumentEleveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouveau type de document'),
        ];
    }

    protected function getEmptyStateActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouveau type de document'),
        ];
    }
}
