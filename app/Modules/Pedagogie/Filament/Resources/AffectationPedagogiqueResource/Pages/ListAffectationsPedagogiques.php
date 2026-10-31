<?php

namespace App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ListAffectationsPedagogiques extends ListRecords
{
    protected static string $resource = AffectationPedagogiqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AmbassadorsDesign::goldenFormModal(CreateAction::make())
                ->label('Nouvelle affectation')
                ->modalHeading('Créer une affectation pédagogique')
                ->createAnother(false)
                ->schema(fn (Schema $schema): Schema => AffectationPedagogiqueResource::form($schema->columns(2)->operation('create')))
                ->using(fn (array $data): Model => AffectationPedagogiqueResource::enregistrer($data))
                ->after(function (ListAffectationsPedagogiques $livewire): void {
                    $livewire->resetTable();
                    $livewire->flushCachedTableRecords();
                }),
        ];
    }
}
