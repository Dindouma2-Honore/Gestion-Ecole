<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\RemiseExonerationResource\Pages;

use App\Modules\Finances\Filament\Resources\RemiseExonerationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRemisesExonerations extends ListRecords
{
    protected static string $resource = RemiseExonerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('eleves')->label('Retour aux élèves')->icon('heroicon-o-arrow-left')->color('gray')->url('/admin/eleves'),
            CreateAction::make()->label('Accorder une remise ou exonération'),
        ];
    }
}
