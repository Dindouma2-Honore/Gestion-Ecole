<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource\Pages;

use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use Filament\Resources\Pages\ListRecords;

class ListGrilleTarifaire extends ListRecords
{
    protected static string $resource = GrilleTarifaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouveau tarif'),
        ];
    }

    protected function getEmptyStateActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouveau tarif'),
        ];
    }
}
