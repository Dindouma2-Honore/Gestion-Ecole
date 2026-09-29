<?php
namespace App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource; use Filament\Actions\CreateAction; use Filament\Resources\Pages\ListRecords;
class ListAffectationsPedagogiques extends ListRecords { protected static string $resource = AffectationPedagogiqueResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
