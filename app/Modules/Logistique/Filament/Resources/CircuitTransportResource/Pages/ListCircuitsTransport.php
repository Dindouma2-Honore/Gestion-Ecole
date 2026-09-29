<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\CircuitTransportResource\Pages;

use App\Modules\Logistique\Filament\Resources\CircuitTransportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCircuitsTransport extends ListRecords
{
    protected static string $resource = CircuitTransportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
