<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\RelancePaiementResource\Pages;

use App\Modules\Finances\Filament\Resources\RelancePaiementResource;
use Filament\Resources\Pages\ListRecords;

class ListRelancesPaiement extends ListRecords
{
    protected static string $resource = RelancePaiementResource::class;
}
