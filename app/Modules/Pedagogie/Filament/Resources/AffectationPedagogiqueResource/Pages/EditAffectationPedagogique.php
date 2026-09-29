<?php
namespace App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource; use Filament\Actions\DeleteAction; use Filament\Resources\Pages\EditRecord;
class EditAffectationPedagogique extends EditRecord { protected static string $resource = AffectationPedagogiqueResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()]; } }
