<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\ParentTuteurResource\Pages;

use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource;
use Filament\Resources\Pages\CreateRecord;

class CreateParentTuteur extends CreateRecord
{
    protected static string $resource = ParentTuteurResource::class;
}
