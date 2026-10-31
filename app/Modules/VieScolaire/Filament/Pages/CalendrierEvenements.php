<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

use App\Modules\VieScolaire\Filament\Resources\CalendrierInstitutionnelResource;
use App\Modules\VieScolaire\Filament\Resources\EvenementScolaireResource;

class CalendrierEvenements extends VieScolaireSection
{
    protected static ?string $slug = 'vie-scolaire/calendrier-evenements';

    protected static ?string $title = 'Calendrier & événements';

    public function getSectionDescription(): string
    {
        return 'Planifiez les fermetures, vacances et grands événements de l’établissement.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Calendrier institutionnel', 'description' => 'Jours fériés, vacances et fermetures', 'icon' => 'heroicon-o-calendar-days', 'url' => CalendrierInstitutionnelResource::getUrl()],
            ['label' => 'Grands événements', 'description' => 'Participants, autorisations et besoins logistiques', 'icon' => 'heroicon-o-calendar', 'url' => EvenementScolaireResource::getUrl()],
        ];
    }
}
