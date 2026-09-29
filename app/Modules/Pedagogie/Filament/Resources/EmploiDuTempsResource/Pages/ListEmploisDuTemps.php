<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmploisDuTemps extends ListRecords
{
    protected static string $resource = EmploiDuTempsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Planifier un cours'),
        ];
    }
}
