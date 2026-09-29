<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\CreneauHoraireResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\CreneauHoraireResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCreneauHoraire extends EditRecord
{
    protected static string $resource = CreneauHoraireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}