<?php

namespace App\Modules\Finances\Filament\Resources\ConfigurationFraisClasseResource\Pages;

use App\Modules\Finances\Filament\Resources\ConfigurationFraisClasseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConfigurationFraisClasse extends EditRecord
{
    protected static string $resource = ConfigurationFraisClasseResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
