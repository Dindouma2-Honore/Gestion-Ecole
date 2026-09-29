<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\TacheResource\Pages;

use App\Modules\Socle\Filament\Resources\TacheResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTache extends EditRecord
{
    protected static string $resource = TacheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
