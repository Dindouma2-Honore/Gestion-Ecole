<?php
namespace App\Modules\Pedagogie\Filament\Resources\ConseilClasseResource\Pages;
use App\Modules\Pedagogie\Filament\Resources\ConseilClasseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListConseilsClasse extends ListRecords { protected static string $resource=ConseilClasseResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
