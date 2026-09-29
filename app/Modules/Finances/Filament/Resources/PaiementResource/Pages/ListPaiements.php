<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\PaiementResource\Pages;

use App\Modules\Finances\Filament\Resources\PaiementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaiements extends ListRecords
{
    protected static string $resource = PaiementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Encaisser un paiement')];
    }
}
