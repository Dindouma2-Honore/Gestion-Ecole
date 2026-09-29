<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\ActivityLogResource\Pages;

use App\Modules\Socle\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;
}
