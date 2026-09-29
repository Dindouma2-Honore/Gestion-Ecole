<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\MessageParentResource\Pages;

use App\Modules\Communication\Filament\Resources\MessageParentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMessageParent extends ViewRecord
{
    protected static string $resource = MessageParentResource::class;
}
