<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\ProgressionResource\Pages;

use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\ProgressionResource;
use App\Modules\Pedagogie\Models\Progression;
use Filament\Resources\Pages\CreateRecord;

class CreateProgression extends CreateRecord
{
    protected static string $resource = ProgressionResource::class;

    /**
     * Passe par ProgressionServiceInterface::saisirProgression() plutôt que
     * par une création Eloquent directe, pour garder la règle métier "la
     * séance doit être dispensée avant de pouvoir renseigner son contenu"
     * (voir SeanceNonDispenseeException).
     */
    protected function handleRecordCreation(array $data): Progression
    {
        return app(ProgressionServiceInterface::class)->saisirProgression(
            seanceId: (int) $data['seance_id'],
            chapitreId: isset($data['chapitre_id']) ? (int) $data['chapitre_id'] : null,
            contenu: $data['contenu_couvert'],
            devoirs: $data['devoirs_donnes'] ?? null,
        );
    }
}
