<?php

namespace App\Modules\Socle\Filament\Resources\JourFerieResource\Pages;

use App\Modules\Socle\Filament\Resources\JourFerieResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJourFerie extends EditRecord
{
    protected static string $resource = JourFerieResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['date_debut'] = $data['date'];
        $data['date_fin'] = $data['date'];

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
