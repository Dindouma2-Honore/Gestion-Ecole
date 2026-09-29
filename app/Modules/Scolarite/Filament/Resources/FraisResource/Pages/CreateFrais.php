<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\FraisResource\Pages;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Filament\Resources\FraisResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFrais extends CreateRecord
{
    protected static string $resource = FraisResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(FraisServiceContract::class)->creerFrais(
            nom: $data['nom'],
            montant: (float) ($data['montant'] ?? 0),
            categorieId: (int) $data['categorie_frais_id'],
            utiliseGrilleTarifaire: (bool) ($data['utilise_grille_tarifaire'] ?? false),
            ordreRepartition: (int) ($data['ordre_repartition'] ?? 999),
        );
    }
}
