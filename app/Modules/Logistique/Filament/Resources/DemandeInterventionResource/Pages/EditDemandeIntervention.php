<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\DemandeInterventionResource\Pages;

use App\Modules\Logistique\Filament\Resources\DemandeInterventionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDemandeIntervention extends EditRecord
{
    protected static string $resource = DemandeInterventionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
