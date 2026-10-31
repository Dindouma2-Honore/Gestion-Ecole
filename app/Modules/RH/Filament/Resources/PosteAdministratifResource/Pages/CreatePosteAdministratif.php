<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\PosteAdministratifResource\Pages;

use App\Modules\RH\Filament\Resources\PosteAdministratifResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePosteAdministratif extends CreateRecord
{
    protected static string $resource = PosteAdministratifResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->color('gray')
            ->title('Fonction créée avec succès')
            ->body('La fonction « '.($this->record?->nom ?? '').' » a été créée et est maintenant disponible.')
            ->send();
    }
}
