<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\TacheResource\Pages;

use App\Modules\Socle\Filament\Resources\TacheResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTache extends CreateRecord
{
    protected static string $resource = TacheResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['createur_id'] = Auth::id();

        return $data;
    }
}
