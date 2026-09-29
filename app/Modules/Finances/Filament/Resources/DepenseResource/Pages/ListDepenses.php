<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\DepenseResource\Pages;

use App\Modules\Finances\Filament\Resources\DepenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepenses extends ListRecords
{
    protected static string $resource = DepenseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
