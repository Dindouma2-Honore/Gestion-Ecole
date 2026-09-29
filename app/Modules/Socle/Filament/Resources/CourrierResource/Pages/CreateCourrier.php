<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\CourrierResource\Pages;

use App\Modules\Socle\Contracts\CourrierServiceContract;
use App\Modules\Socle\Filament\Resources\CourrierResource;
use App\Modules\Socle\Models\Courrier;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCourrier extends CreateRecord
{
    protected static string $resource = CourrierResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(CourrierServiceContract::class);
        if (($data['type'] ?? 'entrant') === 'sortant') {
            $data['cible_config'] = array_filter([
                'user_ids' => $data['user_ids'] ?? null,
                'groupe_id' => $data['groupe_id'] ?? null,
                'parent_ids' => $data['parent_ids'] ?? null,
                'niveau_ids' => $data['niveau_ids'] ?? null,
                'classe_ids' => $data['classe_ids'] ?? null,
            ], fn ($value): bool => $value !== null && $value !== []);
            foreach (['user_ids', 'groupe_id', 'parent_ids', 'niveau_ids', 'classe_ids'] as $champ) { unset($data[$champ]); }
            $courrier = $service->preparerCourrierSortant($data);
        } else {
            $courrier = $service->enregistrerCourrierEntrant([
                'objet' => $data['objet'], 'expediteur' => $data['expediteur'],
                'destinataire' => $data['destinataire'], 'date_limite_reponse' => $data['date_limite_reponse'] ?? null,
            ]);
        }

        return Courrier::findOrFail($courrier->id);
    }
}
