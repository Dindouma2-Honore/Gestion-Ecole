<?php

namespace App\Modules\RH\Filament\Resources\PrimeResource\Pages;

use App\Modules\RH\Filament\Resources\PrimeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePrime extends CreateRecord
{
    protected static string $resource = PrimeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['proposee_par'] = Auth::id() ?? 1;

        return $data;
    }
}
