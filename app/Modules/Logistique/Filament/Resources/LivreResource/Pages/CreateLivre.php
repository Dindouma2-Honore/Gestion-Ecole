<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\LivreResource\Pages;

use App\Modules\Logistique\Filament\Resources\LivreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLivre extends CreateRecord
{
    protected static string $resource = LivreResource::class;
}
