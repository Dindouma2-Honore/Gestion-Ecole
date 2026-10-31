<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\PosteAdministratifResource\Pages;

use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Resources\PosteAdministratifResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPostesAdministratifs extends ListRecords
{
    protected static string $resource = PosteAdministratifResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('personnel')->label('Retour au personnel')->icon('heroicon-o-arrow-left')->color('gray')->url(EmployeResource::getUrl()),
            RhActions::create()->label('Nouvelle fonction'),
        ];
    }
}
