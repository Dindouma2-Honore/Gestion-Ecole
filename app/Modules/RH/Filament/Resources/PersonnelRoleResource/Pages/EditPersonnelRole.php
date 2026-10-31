<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\PersonnelRoleResource\Pages;

use App\Modules\RH\Filament\Resources\PersonnelRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPersonnelRole extends EditRecord
{
    protected static string $resource = PersonnelRoleResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->color('gray')
            ->title('Rôle mis à jour')
            ->body('Le rôle « '.$this->record->name.' » a été mis à jour avec succès.');
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->disabled(fn (): bool => $this->record->users()->exists())->tooltip(fn (): ?string => $this->record->users()->exists() ? 'Ce rôle est encore attribué.' : null)];
    }
}
