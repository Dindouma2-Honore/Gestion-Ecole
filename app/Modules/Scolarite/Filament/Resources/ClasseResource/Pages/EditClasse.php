<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages;

use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Services\ClasseConfigurationService;
use Filament\Resources\Pages\EditRecord;

class EditClasse extends EditRecord
{
    protected static string $resource = ClasseResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...app(ClasseConfigurationService::class)->donnees($this->record)];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['frais'] = (float) config('scolarite.frais_inscription', 15_000);

        return $data;
    }

    protected function afterSave(): void
    {
        app(ClasseConfigurationService::class)->enregistrer($this->record, $this->data);
    }
}
