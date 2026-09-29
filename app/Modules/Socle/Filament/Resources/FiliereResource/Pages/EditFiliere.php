<?php
namespace App\Modules\Socle\Filament\Resources\FiliereResource\Pages; use App\Modules\Socle\Filament\Resources\FiliereResource; use Filament\Actions\DeleteAction; use Filament\Resources\Pages\EditRecord; class EditFiliere extends EditRecord {protected static string $resource=FiliereResource::class; protected function getHeaderActions():array{return [DeleteAction::make()];}}
