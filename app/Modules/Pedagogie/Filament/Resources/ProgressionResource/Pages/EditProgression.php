<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\ProgressionResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\ProgressionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProgression extends EditRecord
{
    protected static string $resource = ProgressionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
