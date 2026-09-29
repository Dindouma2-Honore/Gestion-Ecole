<?php
namespace App\Modules\Pedagogie\Filament\Resources\NoteResource\Pages;
use App\Modules\Pedagogie\Filament\Resources\NoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListNotes extends ListRecords { protected static string $resource = NoteResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
