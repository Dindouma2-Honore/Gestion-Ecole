<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\AnnonceResource\Pages;

use App\Modules\Communication\Filament\Resources\AnnonceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnonce extends CreateRecord
{
    protected static string $resource = AnnonceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['publie_par'] = \Illuminate\Support\Facades\Auth::id() ?? 1;

        return $data;
    }
}
