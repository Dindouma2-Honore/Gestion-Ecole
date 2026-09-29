<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\BulletinResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\BulletinResource;
use Filament\Resources\Pages\ListRecords;

class ListBulletins extends ListRecords
{
    protected static string $resource = BulletinResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
