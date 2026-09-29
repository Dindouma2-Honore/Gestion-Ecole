<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages;

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use App\Modules\Pedagogie\Models\Matiere;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMatiere extends CreateRecord
{
    protected static string $resource = MatiereResource::class;

    /**
     * On passe par le Service (donc par le Contract) plutôt que de laisser
     * Filament faire un Matiere::create() brut, pour que la règle "code unique"
     * (CodeMatiereDejaUtiliseException) reste centralisée à un seul endroit.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $resultat = app(MatiereServiceInterface::class)->creer(
            nom: $data['nom'],
            code: $data['code'],
            coefficient: (float) $data['coefficient_defaut'],
            description: $data['description'] ?? null,
        );

        return Matiere::findOrFail($resultat['id']);
    }
}
