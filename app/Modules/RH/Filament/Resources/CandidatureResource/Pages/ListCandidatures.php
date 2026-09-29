<?php

namespace App\Modules\RH\Filament\Resources\CandidatureResource\Pages;

use App\Modules\RH\Filament\Resources\CandidatureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCandidatures extends ListRecords
{
    protected static string $resource = CandidatureResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle candidature')];
    }
}
