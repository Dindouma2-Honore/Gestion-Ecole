<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\TravauxInfrastructureResource\Pages;

use App\Modules\Logistique\Filament\Resources\TravauxInfrastructureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTravauxInfrastructure extends EditRecord
{
    protected static string $resource = TravauxInfrastructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
