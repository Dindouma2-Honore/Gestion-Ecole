<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\DossierSanteResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\DossierSanteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDossierSante extends CreateRecord
{
    protected static string $resource = DossierSanteResource::class;
}
