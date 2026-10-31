<?php

namespace App\Modules\Assiduite\Filament\Resources\AnomalieAssiduiteResource\Pages;

use App\Modules\Assiduite\Filament\Resources\AnomalieAssiduiteResource;
use Filament\Resources\Pages\EditRecord;

class EditAnomalie extends EditRecord
{
    protected static string $resource = AnomalieAssiduiteResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return self::prepareModalData($data);
    }

    public static function prepareModalData(array $data): array
    {
        if (in_array($data['statut'] ?? null, ['corrigee', 'cloturee', 'ignoree'], true)) {
            $data['resolue_par'] = auth()->id();
            $data['resolue_le'] = now();
        }

        return $data;
    }
}
