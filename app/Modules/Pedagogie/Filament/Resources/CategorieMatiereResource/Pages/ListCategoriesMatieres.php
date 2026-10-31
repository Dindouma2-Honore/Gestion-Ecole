<?php

namespace App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ListCategoriesMatieres extends ListRecords
{
    protected static string $resource = CategorieMatiereResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AmbassadorsDesign::goldenFormModal(CreateAction::make())
                ->label('Nouvelle catégorie')
                ->modalHeading('Créer une catégorie de matière')
                ->createAnother(false)
                ->schema(fn (Schema $schema): Schema => CategorieMatiereResource::form($schema->columns(2)->operation('create')))
                ->using(fn (array $data): Model => CategorieMatiereResource::enregistrer($data))
                ->after(function (ListCategoriesMatieres $livewire): void {
                    $livewire->resetTable();
                    $livewire->flushCachedTableRecords();
                }),
        ];
    }
}
