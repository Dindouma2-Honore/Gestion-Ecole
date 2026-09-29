<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\MessageParentResource\Pages;

use App\Modules\Communication\Filament\Resources\MessageParentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMessageParents extends ListRecords
{
    protected static string $resource = MessageParentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
