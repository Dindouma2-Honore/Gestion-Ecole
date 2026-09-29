<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\GrilleFraisResource\Pages;

use App\Modules\Finances\Filament\Resources\GrilleFraisResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListGrillesFrais extends ListRecords
{
    protected static string $resource = GrilleFraisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('creerFrais')
                ->label('Créer un frais')
                ->icon('heroicon-o-plus-circle')
                ->url(url('/admin/finances/frais/create')),
        ];
    }
}
