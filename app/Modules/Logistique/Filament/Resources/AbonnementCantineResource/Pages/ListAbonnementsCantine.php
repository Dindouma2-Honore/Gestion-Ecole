<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\AbonnementCantineResource\Pages;

use App\Modules\Logistique\Filament\Resources\AbonnementCantineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAbonnementsCantine extends ListRecords
{
    protected static string $resource = AbonnementCantineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
