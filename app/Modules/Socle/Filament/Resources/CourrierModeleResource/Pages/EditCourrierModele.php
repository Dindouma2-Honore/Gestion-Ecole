<?php
namespace App\Modules\Socle\Filament\Resources\CourrierModeleResource\Pages;
use App\Modules\Socle\Filament\Resources\CourrierModeleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditCourrierModele extends EditRecord { protected static string $resource = CourrierModeleResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()]; } }
