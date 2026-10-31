<?php

namespace App\Modules\Logistique\Filament\Resources\VehiculeResource\Pages;

use App\Modules\Logistique\Filament\Resources\VehiculeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicules extends ListRecords
{
    protected static string $resource = VehiculeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
