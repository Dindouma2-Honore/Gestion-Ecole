<?php

namespace App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages;

use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBulletinPaie extends CreateRecord
{
    protected static string $resource = BulletinPaieResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['annee_scolaire_id'])) {
            $anneeScolaireService = app(AnneeScolaireServiceContract::class);
            try {
                $data['annee_scolaire_id'] = $anneeScolaireService->getAnneeCouranteId();
            } catch (\Throwable) {
                $data['annee_scolaire_id'] = 1;
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $existe = BulletinPaie::query()
            ->where('employe_id', $data['employe_id'])
            ->where('mois', $data['mois'])
            ->where('annee', $data['annee'])
            ->exists();

        if ($existe) {
            Notification::make()
                ->title('Bulletin déjà existant')
                ->body('Un bulletin existe déjà pour cet employé sur cette période.')
                ->danger()
                ->send();

            $this->halt();
        }

        return parent::handleRecordCreation($data);
    }
}
