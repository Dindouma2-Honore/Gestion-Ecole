<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\RoleResource\Pages;

use App\Modules\Socle\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;
}
