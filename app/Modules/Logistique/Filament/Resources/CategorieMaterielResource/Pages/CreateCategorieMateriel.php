<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\CategorieMaterielResource\Pages;

use App\Modules\Logistique\Filament\Resources\CategorieMaterielResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategorieMateriel extends CreateRecord
{
    protected static string $resource = CategorieMaterielResource::class;
}
