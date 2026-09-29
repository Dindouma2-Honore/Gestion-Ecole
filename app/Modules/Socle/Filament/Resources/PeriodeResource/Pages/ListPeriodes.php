<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\PeriodeResource\Pages;

use App\Modules\Socle\Filament\Resources\PeriodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPeriodes extends ListRecords
{
    protected static string $resource = PeriodeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
