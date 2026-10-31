<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\PersonnelRoleResource\Pages;

use App\Modules\RH\Filament\Resources\PersonnelRoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonnelRole extends CreateRecord
{
    protected static string $resource = PersonnelRoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->color('gray')
            ->title('Rôle créé avec succès')
            ->body('Le rôle « '.($this->record?->name ?? '').' » a été créé et est maintenant disponible.')
            ->send();
    }
}
