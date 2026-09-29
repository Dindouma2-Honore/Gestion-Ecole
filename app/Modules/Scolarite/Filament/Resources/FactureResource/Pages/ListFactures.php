<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\FactureResource\Pages;

use App\Modules\Scolarite\Filament\Resources\FactureResource;
use Filament\Resources\Pages\ListRecords;

class ListFactures extends ListRecords
{
    protected static string $resource = FactureResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
