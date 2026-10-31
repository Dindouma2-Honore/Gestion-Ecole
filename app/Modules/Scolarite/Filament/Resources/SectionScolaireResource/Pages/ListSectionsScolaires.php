<?php

namespace App\Modules\Scolarite\Filament\Resources\SectionScolaireResource\Pages;

use App\Modules\Scolarite\Filament\Resources\SectionScolaireResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSectionsScolaires extends ListRecords
{
    protected static string $resource = SectionScolaireResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
