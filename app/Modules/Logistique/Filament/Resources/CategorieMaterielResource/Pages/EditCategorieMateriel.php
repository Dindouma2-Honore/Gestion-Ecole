<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\CategorieMaterielResource\Pages;

use App\Modules\Logistique\Filament\Resources\CategorieMaterielResource;
use Filament\Resources\Pages\EditRecord;

class EditCategorieMateriel extends EditRecord
{
    protected static string $resource = CategorieMaterielResource::class;
}
