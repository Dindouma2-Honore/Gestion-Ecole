<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EquipementResource\Pages;

use App\Modules\Logistique\Filament\Resources\EquipementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipement extends CreateRecord
{
    protected static string $resource = EquipementResource::class;
}
