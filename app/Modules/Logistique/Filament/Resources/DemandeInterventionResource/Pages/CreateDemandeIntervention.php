<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\DemandeInterventionResource\Pages;

use App\Modules\Logistique\Filament\Resources\DemandeInterventionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDemandeIntervention extends CreateRecord
{
    protected static string $resource = DemandeInterventionResource::class;
}
