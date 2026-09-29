<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\CreneauHoraireResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\CreneauHoraireResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCreneauHoraire extends CreateRecord
{
    protected static string $resource = CreneauHoraireResource::class;
}