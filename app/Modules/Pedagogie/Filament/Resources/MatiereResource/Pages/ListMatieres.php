<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMatieres extends ListRecords
{
    protected static string $resource = MatiereResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouvelle matière'),
        ];
    }
}
