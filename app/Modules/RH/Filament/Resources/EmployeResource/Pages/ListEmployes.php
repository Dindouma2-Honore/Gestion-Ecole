<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\EmployeResource\Pages;

use App\Filament\Concerns\HasCardListLayout;
use App\Modules\RH\Filament\Resources\CategoriePersonnelResource;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Resources\PersonnelRoleResource;
use App\Modules\RH\Filament\Resources\PosteAdministratifResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployes extends ListRecords
{
    use HasCardListLayout;

    protected static string $resource = EmployeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cardLayoutAction(),
            Actions\Action::make('roles')->label('Rôles')->icon('heroicon-o-shield-check')->color('gray')->url(PersonnelRoleResource::getUrl()),
            Actions\Action::make('fonctions')->label('Fonctions')->icon('heroicon-o-briefcase')->color('gray')->url(PosteAdministratifResource::getUrl()),
            Actions\Action::make('categories')->label('Catégories')->icon('heroicon-o-tag')->color('gray')->url(CategoriePersonnelResource::getUrl()),
            Actions\CreateAction::make()->label('Nouveau membre du personnel'),
        ];
    }
}
