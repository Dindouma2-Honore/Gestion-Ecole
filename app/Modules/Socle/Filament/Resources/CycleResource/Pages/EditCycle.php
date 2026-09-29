<?php
namespace App\Modules\Socle\Filament\Resources\CycleResource\Pages; use App\Modules\Socle\Filament\Resources\CycleResource; use Filament\Actions\DeleteAction; use Filament\Resources\Pages\EditRecord; class EditCycle extends EditRecord {protected static string $resource=CycleResource::class; protected function getHeaderActions():array{return [DeleteAction::make()];}}
