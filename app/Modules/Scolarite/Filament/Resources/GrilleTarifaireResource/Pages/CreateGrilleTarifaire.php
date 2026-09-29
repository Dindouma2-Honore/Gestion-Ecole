<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource\Pages;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource;
use App\Modules\Scolarite\Models\GrilleTarifaire;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGrilleTarifaire extends CreateRecord
{
    protected static string $resource = GrilleTarifaireResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        app(FraisServiceContract::class)->definirTarifClasse(
            fraisId: (int) $data['frais_id'],
            classeId: (int) $data['classe_id'],
            anneeScolaireId: (int) $data['annee_scolaire_id'],
            montant: (float) $data['montant'],
        );

        return GrilleTarifaire::where('frais_id', $data['frais_id'])
            ->where('classe_id', $data['classe_id'])
            ->where('annee_scolaire_id', $data['annee_scolaire_id'])
            ->firstOrFail();
    }
}
