<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\CategorieFraisResource\Pages;

use App\Modules\Scolarite\Filament\Resources\CategorieFraisResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use Filament\Resources\Pages\ListRecords;

class ListCategoriesFrais extends ListRecords
{
    protected static string $resource = CategorieFraisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouvelle catégorie'),
        ];
    }

    protected function getEmptyStateActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouvelle catégorie'),
        ];
    }
}
