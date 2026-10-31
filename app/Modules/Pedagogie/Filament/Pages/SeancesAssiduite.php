<?php

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Filament\Resources\AnomalieAppelResource;
use App\Modules\Pedagogie\Filament\Resources\PresenceResource;
use App\Modules\Pedagogie\Filament\Resources\SeanceResource;

class SeancesAssiduite extends PedagogieSection
{
    protected static ?string $slug = 'pedagogie/seances-assiduite';

    protected static ?string $title = 'Séances & appels';

    public function getSectionDescription(): string
    {
        return 'Suivez les cours réellement dispensés, les appels et leurs anomalies.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Séances', 'description' => 'Mes séances et actions du jour', 'icon' => 'heroicon-o-presentation-chart-bar', 'url' => SeanceResource::getUrl()],
            ['label' => 'Appels & justifications', 'description' => 'Présences, absences et retards', 'icon' => 'heroicon-o-check-badge', 'url' => PresenceResource::getUrl()],
            ['label' => 'Appels manquants', 'description' => 'Anomalies nécessitant une action', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => AnomalieAppelResource::getUrl()],
        ];
    }
}
