<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\NiveauResource\Pages;

use App\Modules\Socle\Filament\Resources\NiveauResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNiveaux extends ListRecords
{
    protected static string $resource = NiveauResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
