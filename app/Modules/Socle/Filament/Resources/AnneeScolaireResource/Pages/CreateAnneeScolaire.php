<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\AnneeScolaireResource\Pages;

use App\Modules\Socle\Filament\Resources\AnneeScolaireResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnneeScolaire extends CreateRecord
{
    protected static string $resource = AnneeScolaireResource::class;
}
