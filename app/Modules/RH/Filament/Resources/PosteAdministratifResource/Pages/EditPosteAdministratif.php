<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\PosteAdministratifResource\Pages;

use App\Modules\RH\Filament\Resources\PosteAdministratifResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPosteAdministratif extends EditRecord
{
    protected static string $resource = PosteAdministratifResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->disabled(fn (): bool => $this->record->employes()->exists())->tooltip(fn (): ?string => $this->record->employes()->exists() ? 'Cette fonction est encore attribuée.' : null)];
    }
}
