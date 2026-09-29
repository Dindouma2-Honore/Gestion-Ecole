<?php
namespace App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource; use Filament\Actions\CreateAction; use Filament\Resources\Pages\ListRecords;
class ListOffresPedagogiques extends ListRecords { protected static string $resource = OffrePedagogiqueResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
