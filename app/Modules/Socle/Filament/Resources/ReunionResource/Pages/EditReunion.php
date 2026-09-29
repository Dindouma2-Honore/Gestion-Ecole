<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\ReunionResource\Pages;

use App\Modules\Socle\Filament\Resources\ReunionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReunion extends EditRecord
{
    protected static string $resource = ReunionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
