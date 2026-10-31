<?php

namespace App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ListOffresPedagogiques extends ListRecords
{
    protected static string $resource = OffrePedagogiqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AmbassadorsDesign::goldenFormModal(CreateAction::make())
                ->label('Nouvelle offre pédagogique')
                ->modalHeading('Créer une offre pédagogique')
                ->createAnother(false)
                ->schema(fn (Schema $schema): Schema => OffrePedagogiqueResource::form($schema->columns(2)->operation('create')))
                ->using(fn (array $data): Model => OffrePedagogiqueResource::enregistrer($data))
                ->after(function (ListOffresPedagogiques $livewire): void {
                    $livewire->resetTable();
                    $livewire->flushCachedTableRecords();
                }),
        ];
    }
}
