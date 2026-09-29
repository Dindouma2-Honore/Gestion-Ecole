<?php

namespace App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoriesMatieres extends ListRecords
{
    protected static string $resource = CategorieMatiereResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
