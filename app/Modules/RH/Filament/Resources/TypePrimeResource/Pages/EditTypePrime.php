<?php

namespace App\Modules\RH\Filament\Resources\TypePrimeResource\Pages;

use App\Modules\RH\Filament\Resources\TypePrimeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTypePrime extends EditRecord
{
    protected static string $resource = TypePrimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
