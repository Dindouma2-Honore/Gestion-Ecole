<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\PresenceResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\PresenceResource;
use Filament\Resources\Pages\ListRecords;

class ListPresences extends ListRecords
{
    protected static string $resource = PresenceResource::class;
}
