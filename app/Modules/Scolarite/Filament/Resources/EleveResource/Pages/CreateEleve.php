<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\EleveResource\Pages;

use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Models\Eleve;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEleve extends CreateRecord
{
    protected static string $resource = EleveResource::class;

    /**
     * On passe par le Service (donc par le Contract), pour que le statut
     * initial et toute règle future (détection de doublons, etc.) restent
     * centralisés à un seul endroit plutôt que dupliqués dans Filament.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $resultat = app(EleveServiceInterface::class)->creer($data);

        return Eleve::findOrFail($resultat['id']);
    }
}
