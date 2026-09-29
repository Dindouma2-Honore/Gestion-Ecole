<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\ProgrammeResource\Pages;

use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource;
use App\Modules\Pedagogie\Models\Programme;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProgramme extends CreateRecord
{
    protected static string $resource = ProgrammeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $resultat = app(ProgrammeServiceInterface::class)->creerProgramme(
            matiereId: (int) $data['matiere_id'],
            niveauId: (int) $data['niveau_id'],
            anneeScolaireId: (int) $data['annee_scolaire_id'],
            titre: $data['titre'],
            description: $data['description'] ?? null,
        );

        return Programme::findOrFail($resultat['id']);
    }
}
