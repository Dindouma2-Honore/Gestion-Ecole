<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\SalleResource\Pages;

use App\Modules\Logistique\Filament\Resources\SalleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalle extends CreateRecord
{
    protected static string $resource = SalleResource::class;
}
