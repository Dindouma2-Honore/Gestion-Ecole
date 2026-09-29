<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource\Pages;

use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrilleTarifaire extends ListRecords
{
    protected static string $resource = GrilleTarifaireResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
