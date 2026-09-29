<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\CategorieFraisResource\Pages;

use App\Modules\Scolarite\Filament\Resources\CategorieFraisResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoriesFrais extends ListRecords
{
    protected static string $resource = CategorieFraisResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
