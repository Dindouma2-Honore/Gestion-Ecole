<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\TravauxInfrastructureResource\Pages;

use App\Modules\Logistique\Filament\Resources\TravauxInfrastructureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTravauxInfrastructure extends CreateRecord
{
    protected static string $resource = TravauxInfrastructureResource::class;
}
