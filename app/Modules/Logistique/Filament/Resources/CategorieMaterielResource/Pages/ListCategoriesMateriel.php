<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\CategorieMaterielResource\Pages;

use App\Modules\Logistique\Filament\Resources\CategorieMaterielResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoriesMateriel extends ListRecords
{
    protected static string $resource = CategorieMaterielResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
