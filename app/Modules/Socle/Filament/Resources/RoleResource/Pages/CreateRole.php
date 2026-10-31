<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\RoleResource\Pages;

use App\Modules\Socle\Filament\Resources\RoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->color('gray')
            ->title('Rôle créé avec succès')
            ->body('Le rôle « '.($this->record?->name ?? '').' » a été créé et est maintenant disponible.')
            ->send();
    }
}
