<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\FraisResource\Pages;

use App\Modules\Scolarite\Filament\Resources\FraisResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use Filament\Resources\Pages\ListRecords;

class ListFrais extends ListRecords
{
    protected static string $resource = FraisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouveau frais'),
        ];
    }

    protected function getEmptyStateActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouveau frais'),
        ];
    }
}
