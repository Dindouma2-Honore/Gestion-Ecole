<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\CategoriePersonnelResource\Pages;

use App\Modules\RH\Filament\Resources\CategoriePersonnelResource;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCategoriesPersonnel extends ListRecords
{
    protected static string $resource = CategoriePersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('personnel')->label('Retour au personnel')->icon('heroicon-o-arrow-left')->color('gray')->url(EmployeResource::getUrl()),
            RhActions::create()->label('Nouvelle catégorie libre'),
        ];
    }
}
