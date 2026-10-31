<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\AvanceSalaireResource\Pages;

use App\Modules\RH\Filament\Resources\AvanceSalaireResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Resources\Pages\ListRecords;

class ListAvancesSalaire extends ListRecords
{
    protected static string $resource = AvanceSalaireResource::class;

    protected function getHeaderActions(): array
    {
        return [RhActions::create()];
    }
}
