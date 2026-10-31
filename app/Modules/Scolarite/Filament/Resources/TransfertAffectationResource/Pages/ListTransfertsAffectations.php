<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource\Pages;

use App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use Filament\Resources\Pages\ListRecords;

class ListTransfertsAffectations extends ListRecords
{
    protected static string $resource = TransfertAffectationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ScolariteActions::create()->label('Transférer un élève'),
        ];
    }

    protected function getEmptyStateActions(): array
    {
        return [
            ScolariteActions::create()->label('Transférer un élève'),
        ];
    }
}
