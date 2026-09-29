<?php
namespace App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource; use Filament\Actions\DeleteAction; use Filament\Resources\Pages\EditRecord;
class EditOffrePedagogique extends EditRecord { protected static string $resource = OffrePedagogiqueResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()]; } }
