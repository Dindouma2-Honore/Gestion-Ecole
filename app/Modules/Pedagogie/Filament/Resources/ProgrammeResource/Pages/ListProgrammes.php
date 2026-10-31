<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\ProgrammeResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource;
use Filament\Resources\Pages\ListRecords;

class ListProgrammes extends ListRecords
{
    protected static string $resource = ProgrammeResource::class;

    protected function getHeaderActions(): array
    {
        return [ProgrammeResource::importAction()];
    }
}
