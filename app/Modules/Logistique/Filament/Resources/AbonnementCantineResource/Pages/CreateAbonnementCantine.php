<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\AbonnementCantineResource\Pages;

use App\Modules\Logistique\Filament\Resources\AbonnementCantineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAbonnementCantine extends CreateRecord
{
    protected static string $resource = AbonnementCantineResource::class;
}
