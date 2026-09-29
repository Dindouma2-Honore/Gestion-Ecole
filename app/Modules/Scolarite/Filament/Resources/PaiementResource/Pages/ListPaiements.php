<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\PaiementResource\Pages;

use App\Modules\Scolarite\Filament\Resources\PaiementResource;
use Filament\Resources\Pages\ListRecords;

class ListPaiements extends ListRecords
{
    protected static string $resource = PaiementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PaiementResource::creerVersementAction(),
        ];
    }

    // Pas de page "create" enregistrée (voir PaiementResource::getPages()) :
    // le bouton par défaut de l'état vide doit aussi pointer vers notre
    // action personnalisée plutôt que vers une route ../create inexistante.
    protected function getEmptyStateActions(): array
    {
        return [
            PaiementResource::creerVersementAction(),
        ];
    }
}
