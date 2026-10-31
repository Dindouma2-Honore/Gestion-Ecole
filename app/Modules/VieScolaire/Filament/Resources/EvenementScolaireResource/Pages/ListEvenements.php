<?php

namespace App\Modules\VieScolaire\Filament\Resources\EvenementScolaireResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\EvenementScolaireResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvenements extends ListRecords
{
    protected static string $resource = EvenementScolaireResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
