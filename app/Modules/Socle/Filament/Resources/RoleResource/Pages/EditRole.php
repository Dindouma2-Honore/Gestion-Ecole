<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\RoleResource\Pages;

use App\Modules\Socle\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->color('gray')
            ->title('Rôle mis à jour')
            ->body('Le rôle « '.$this->record->name.' » a été mis à jour avec succès.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
