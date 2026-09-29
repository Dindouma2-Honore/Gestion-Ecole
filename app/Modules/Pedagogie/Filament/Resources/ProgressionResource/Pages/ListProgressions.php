<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\ProgressionResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\ProgressionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgressions extends ListRecords
{
    protected static string $resource = ProgressionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Renseigner la progression d’une séance'),
        ];
    }
}
