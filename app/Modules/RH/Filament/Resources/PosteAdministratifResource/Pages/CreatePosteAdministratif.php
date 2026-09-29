<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\PosteAdministratifResource\Pages;

use App\Modules\RH\Filament\Resources\PosteAdministratifResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePosteAdministratif extends CreateRecord
{
    protected static string $resource = PosteAdministratifResource::class;
}
