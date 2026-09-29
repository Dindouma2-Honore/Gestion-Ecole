<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EvenementResource\Pages;

use App\Modules\Logistique\Filament\Resources\EvenementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvenement extends EditRecord
{
    protected static string $resource = EvenementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
