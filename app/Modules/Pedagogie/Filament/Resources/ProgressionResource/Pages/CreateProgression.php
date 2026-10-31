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
     * Pré-remplit le formulaire avec le seance_id passé en query string
     * (ex: /pedagogie/progressions/create?seance_id=42 depuis SeanceResource).
     */
    public function mount(): void
    {
        parent::mount();

        $seanceId = request()->query('seance_id');
        if ($seanceId) {
            $this->form->fill(['seance_id' => (int) $seanceId]);
        }
    }

    /**
     * Passe par ProgressionServiceInterface::saisirProgression() plutôt que
     * par une création Eloquent directe, pour garder la règle métier "la
     * séance doit être dispensée avant de pouvoir renseigner son contenu"
     * (voir SeanceNonDispenseeException).
     */
    protected function handleRecordCreation(array $data): Progression
    {
        $devoirData = null;
        if (! empty($data['devoir_donne']) && ! empty($data['devoir_titre'])) {
            $devoirData = [
                'titre' => $data['devoir_titre'],
                'description' => $data['devoirs_donnes'] ?? null,
                'type_devoir' => $data['devoir_type'] ?? 'exercice',
                'date_limite' => $data['devoir_date_limite'] ?? null,
            ];
        }

        /** @var Progression $progression */
        $progression = app(ProgressionServiceInterface::class)->saisirProgression(
            seanceId: (int) $data['seance_id'],
            chapitreId: isset($data['chapitre_id']) ? (int) $data['chapitre_id'] : null,
            contenu: $data['contenu_couvert'],
            devoirs: $data['devoirs_donnes'] ?? null,
            chapitreTermine: (bool) ($data['chapitre_termine'] ?? false),
            leconId: isset($data['lecon_id']) ? (int) $data['lecon_id'] : null,
            statutElement: (string) ($data['statut_element'] ?? 'terminee'),
            dureeMinutes: isset($data['duree_minutes']) ? (int) $data['duree_minutes'] : null,
            devoirData: $devoirData,
        );

        return $progression;
    }
}
