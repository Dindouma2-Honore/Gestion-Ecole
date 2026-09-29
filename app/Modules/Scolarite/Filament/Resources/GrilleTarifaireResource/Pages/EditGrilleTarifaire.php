<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource\Pages;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditGrilleTarifaire extends EditRecord
{
    protected static string $resource = GrilleTarifaireResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(FraisServiceContract::class)->definirTarifClasse(
            fraisId: (int) $data['frais_id'],
            classeId: (int) $data['classe_id'],
            anneeScolaireId: (int) $data['annee_scolaire_id'],
            montant: (float) $data['montant'],
        );

        return $record->fresh();
    }
}
