<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\ParentTuteurResource\Pages;

use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use Filament\Resources\Pages\ViewRecord;

class ViewParentTuteur extends ViewRecord
{
    protected static string $resource = ParentTuteurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ScolariteActions::edit()->label('Modifier le parent'),
        ];
    }
}
