<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\PersonnelRoleResource\Pages;

use App\Modules\RH\Filament\Resources\PersonnelRoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonnelRole extends CreateRecord
{
    protected static string $resource = PersonnelRoleResource::class;
}
