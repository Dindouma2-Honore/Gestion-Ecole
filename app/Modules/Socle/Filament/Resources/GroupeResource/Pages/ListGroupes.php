<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\GroupeResource\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Socle\Filament\Resources\GroupeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupes extends ListRecords
{
    protected static string $resource = GroupeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AmbassadorsDesign::goldenFormModal(Actions\CreateAction::make())
                ->label('Créer un groupe')
                ->modalHeading('Créer un groupe d’utilisateurs')
                ->createAnother(false),
        ];
    }
}
