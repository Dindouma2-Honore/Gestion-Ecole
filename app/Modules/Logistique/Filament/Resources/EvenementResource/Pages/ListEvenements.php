<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EvenementResource\Pages;

use App\Modules\Logistique\Filament\Resources\EvenementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEvenements extends ListRecords
{
    protected static string $resource = EvenementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
