<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use Filament\Resources\Pages\EditRecord;

class EditMatiere extends EditRecord
{
    protected static string $resource = MatiereResource::class;
}
