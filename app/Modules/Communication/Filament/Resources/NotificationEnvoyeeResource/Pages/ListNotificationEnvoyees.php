<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\NotificationEnvoyeeResource\Pages;

use App\Modules\Communication\Filament\Resources\NotificationEnvoyeeResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationEnvoyees extends ListRecords
{
    protected static string $resource = NotificationEnvoyeeResource::class;
}
