<?php
namespace App\Modules\Pedagogie\Filament\Resources\ConseilClasseResource\Pages;
use App\Modules\Pedagogie\Filament\Resources\ConseilClasseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditConseilClasse extends EditRecord { protected static string $resource=ConseilClasseResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()]; } }
