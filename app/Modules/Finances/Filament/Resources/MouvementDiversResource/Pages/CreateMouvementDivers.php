<?php

namespace App\Modules\Finances\Filament\Resources\MouvementDiversResource\Pages;

use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Filament\Resources\MouvementDiversResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMouvementDivers extends CreateRecord
{
    protected static string $resource = MouvementDiversResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CaisseServiceContract::class)->enregistrerMouvement(type: $data['type'], montant: (float) $data['montant'], justificatif: trim($data['motif']), rubrique: $data['type'] === 'encaissement' ? 'Entrée diverse' : 'Sortie diverse', moduleOrigine: 'Finances', sousModule: 'Mouvements divers');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
