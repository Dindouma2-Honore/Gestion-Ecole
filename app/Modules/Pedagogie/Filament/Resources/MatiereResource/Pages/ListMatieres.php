<?php

namespace App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ListMatieres extends ListRecords
{
    protected static string $resource = MatiereResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('categories')
                ->label('Gérer les catégories')
                ->icon('heroicon-o-tag')
                ->url(CategorieMatiereResource::getUrl()),
            AmbassadorsDesign::goldenFormModal(CreateAction::make())
                ->label('Nouvelle matière')
                ->modalHeading('Créer une matière')
                ->createAnother(false)
                ->schema(fn (Schema $schema): Schema => MatiereResource::form($schema->columns(2)->operation('create')))
                ->using(fn (array $data): Model => MatiereResource::enregistrer($data))
                ->after(function (ListMatieres $livewire): void {
                    $livewire->resetTable();
                    $livewire->flushCachedTableRecords();
                }),
        ];
    }
}
