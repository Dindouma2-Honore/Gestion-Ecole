<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\CourrierResource\Pages;

use App\Modules\Socle\Filament\Resources\CourrierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCourriers extends ListRecords
{
    protected static string $resource = CourrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
