<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\GroupeResource\Pages;

use App\Modules\Socle\Filament\Resources\GroupeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupes extends ListRecords
{
    protected static string $resource = GroupeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
