<?php

namespace App\Modules\Socle\Filament\Resources\JourFerieResource\Pages;

use App\Modules\Socle\Filament\Resources\JourFerieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJoursFeries extends ListRecords
{
    protected static string $resource = JourFerieResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
