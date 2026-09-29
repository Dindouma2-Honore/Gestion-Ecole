<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\FraisResource\Pages;

use App\Modules\Scolarite\Filament\Resources\FraisResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFrais extends ListRecords
{
    protected static string $resource = FraisResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
