<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\GrilleFraisResource\Pages;

use App\Modules\Finances\Filament\Resources\GrilleFraisResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGrilleFrais extends CreateRecord
{
    protected static string $resource = GrilleFraisResource::class;
}
