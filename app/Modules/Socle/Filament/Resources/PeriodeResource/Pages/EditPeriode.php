<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\PeriodeResource\Pages;

use App\Modules\Socle\Filament\Resources\PeriodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPeriode extends EditRecord
{
    protected static string $resource = PeriodeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
