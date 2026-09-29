<?php

namespace App\Modules\Finances\Filament\Resources\ConfigurationFraisClasseResource\Pages;

use App\Modules\Finances\Filament\Resources\ConfigurationFraisClasseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConfigurationsFraisClasse extends ListRecords
{
    protected static string $resource = ConfigurationFraisClasseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
