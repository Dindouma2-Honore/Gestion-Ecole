<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\DocumentResource\Pages;

use App\Modules\Socle\Filament\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Créer un document')
                ->icon('heroicon-o-plus'),
        ];
    }
}
