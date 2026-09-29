<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\SeanceResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\SeanceResource;
use Filament\Resources\Pages\ListRecords;

class ListSeances extends ListRecords
{
    protected static string $resource = SeanceResource::class;
}
