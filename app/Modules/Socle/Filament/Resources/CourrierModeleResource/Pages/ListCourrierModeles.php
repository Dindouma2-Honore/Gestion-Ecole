<?php
namespace App\Modules\Socle\Filament\Resources\CourrierModeleResource\Pages;
use App\Modules\Socle\Filament\Resources\CourrierModeleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListCourrierModeles extends ListRecords { protected static string $resource = CourrierModeleResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
