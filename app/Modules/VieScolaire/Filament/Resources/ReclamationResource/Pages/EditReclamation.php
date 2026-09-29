<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\ReclamationResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\ReclamationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReclamation extends EditRecord
{
    protected static string $resource = ReclamationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
