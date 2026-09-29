<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\DossierSanteResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\DossierSanteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDossiersSante extends ListRecords
{
    protected static string $resource = DossierSanteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
