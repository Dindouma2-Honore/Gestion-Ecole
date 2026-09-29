<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\AnneeScolaireResource\Pages;

use App\Modules\Socle\Filament\Resources\AnneeScolaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnneeScolaires extends ListRecords
{
    protected static string $resource = AnneeScolaireResource::class;

    protected ?string $subheading = 'Préparez, activez et archivez les années sans perdre le contexte historique.';

    protected function getHeaderWidgets(): array
    {
        return AnneeScolaireResource::getWidgets();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
