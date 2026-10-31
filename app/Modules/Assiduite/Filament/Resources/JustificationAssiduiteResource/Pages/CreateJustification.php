<?php

namespace App\Modules\Assiduite\Filament\Resources\JustificationAssiduiteResource\Pages;

use App\Modules\Assiduite\Filament\Resources\JustificationAssiduiteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJustification extends CreateRecord
{
    protected static string $resource = JustificationAssiduiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['soumis_par'] = auth()->id();
        $data['soumis_le'] = now();

        return $data;
    }
}
