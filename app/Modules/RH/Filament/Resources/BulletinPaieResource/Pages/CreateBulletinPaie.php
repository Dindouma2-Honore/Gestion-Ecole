<?php

namespace App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages;

use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBulletinPaie extends CreateRecord
{
    protected static string $resource = BulletinPaieResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['annee_scolaire_id'])) {
            $anneeScolaireService = app(\App\Modules\Socle\Contracts\AnneeScolaireServiceContract::class);
            try {
                $data['annee_scolaire_id'] = $anneeScolaireService->getAnneeCouranteId();
            } catch (\Throwable) {
                $data['annee_scolaire_id'] = 1;
            }
        }

        return $data;
    }
}
