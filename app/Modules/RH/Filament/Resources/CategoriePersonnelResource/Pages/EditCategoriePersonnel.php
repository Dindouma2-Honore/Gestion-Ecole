<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\CategoriePersonnelResource\Pages;

use App\Modules\RH\Filament\Resources\CategoriePersonnelResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategoriePersonnel extends EditRecord
{
    protected static string $resource = CategoriePersonnelResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->color('gray')
            ->title('Catégorie mise à jour')
            ->body('La catégorie « '.$this->record->nom.' » a été mise à jour avec succès.');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (): bool => $this->record->employes()->exists())
                ->tooltip(fn (): ?string => $this->record->employes()->exists() ? 'Cette catégorie est attribuée à du personnel.' : null),
        ];
    }
}
