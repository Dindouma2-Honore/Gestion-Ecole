<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\AnneeScolaireResource\Pages;

use App\Modules\Socle\Filament\Resources\AnneeScolaireResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnneeScolaire extends EditRecord
{
    protected static string $resource = AnneeScolaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
