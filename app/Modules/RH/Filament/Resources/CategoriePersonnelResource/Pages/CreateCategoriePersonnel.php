<?php

namespace App\Modules\RH\Filament\Resources\CategoriePersonnelResource\Pages;

use App\Modules\RH\Filament\Resources\CategoriePersonnelResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoriePersonnel extends CreateRecord
{
    protected static string $resource = CategoriePersonnelResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->color('gray')
            ->title('Catégorie créée avec succès')
            ->body('La catégorie « '.($this->record?->nom ?? '').' » a été créée et est maintenant disponible.')
            ->send();
    }
}
