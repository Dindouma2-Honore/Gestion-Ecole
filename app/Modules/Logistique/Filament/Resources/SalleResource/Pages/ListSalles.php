<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\SalleResource\Pages;

use App\Modules\Logistique\Filament\Resources\SalleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalles extends ListRecords
{
    protected static string $resource = SalleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
