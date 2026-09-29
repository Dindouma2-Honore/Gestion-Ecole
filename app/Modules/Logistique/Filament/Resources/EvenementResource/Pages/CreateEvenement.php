<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EvenementResource\Pages;

use App\Modules\Logistique\Filament\Resources\EvenementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvenement extends CreateRecord
{
    protected static string $resource = EvenementResource::class;
}
