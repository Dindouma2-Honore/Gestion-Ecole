<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\ProgrammeResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource;
use Filament\Resources\Pages\EditRecord;

class EditProgramme extends EditRecord
{
    protected static string $resource = ProgrammeResource::class;
}
