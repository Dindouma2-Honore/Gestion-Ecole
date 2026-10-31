<?php

namespace App\Modules\Logistique\Filament\Resources\PlanMaintenancePreventiveResource\Pages;

use App\Modules\Logistique\Filament\Resources\PlanMaintenancePreventiveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlansMaintenance extends ListRecords
{
    protected static string $resource = PlanMaintenancePreventiveResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
