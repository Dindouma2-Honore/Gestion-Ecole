<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\EmployeResource\Pages;

use App\Modules\RH\Filament\Resources\EmployeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewEmploye extends ViewRecord
{
    protected static string $resource = EmployeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Modifier'),
            DeleteAction::make()->label('Supprimer'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            View::make('rh::filament.pages.employe-details-table')
                ->viewData(fn (): array => ['record' => $this->record->loadMissing(['role', 'posteAdministratif', 'contratPrincipal', 'categories', 'diplomes', 'sanctions', 'historiquePostes', 'candidature'])])
                ->columnSpanFull(),
        ]);
    }
}
