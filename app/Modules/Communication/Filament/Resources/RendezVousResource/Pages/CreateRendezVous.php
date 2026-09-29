<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\RendezVousResource\Pages;

use App\Modules\Communication\Filament\Resources\RendezVousResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRendezVous extends CreateRecord
{
    protected static string $resource = RendezVousResource::class;
}
