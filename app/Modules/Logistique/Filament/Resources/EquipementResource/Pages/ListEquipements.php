<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EquipementResource\Pages;

use App\Modules\Logistique\Filament\Resources\EquipementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipements extends ListRecords
{
    protected static string $resource = EquipementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
