<?php

namespace App\Modules\RH\Filament\Resources\SanctionPersonnelResource\Pages;

use App\Modules\RH\Filament\Resources\SanctionPersonnelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSanctionPersonnel extends CreateRecord
{
    protected static string $resource = SanctionPersonnelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id() ?? 1;

        return $data;
    }
}
