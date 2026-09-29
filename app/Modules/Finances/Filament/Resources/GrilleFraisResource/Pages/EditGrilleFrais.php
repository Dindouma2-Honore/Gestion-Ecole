<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\GrilleFraisResource\Pages;

use App\Modules\Finances\Filament\Resources\GrilleFraisResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrilleFrais extends EditRecord
{
    protected static string $resource = GrilleFraisResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
