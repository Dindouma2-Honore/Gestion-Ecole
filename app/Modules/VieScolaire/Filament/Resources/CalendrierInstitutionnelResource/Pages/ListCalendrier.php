<?php

namespace App\Modules\VieScolaire\Filament\Resources\CalendrierInstitutionnelResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\CalendrierInstitutionnelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalendrier extends ListRecords
{
    protected static string $resource = CalendrierInstitutionnelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
