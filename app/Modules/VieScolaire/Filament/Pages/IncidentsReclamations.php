<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

use App\Modules\VieScolaire\Filament\Resources\IncidentVieScolaireResource;
use App\Modules\VieScolaire\Filament\Resources\ReclamationResource;

class IncidentsReclamations extends VieScolaireSection
{
    protected static ?string $slug = 'vie-scolaire/incidents-reclamations';

    protected static ?string $title = 'Incidents & réclamations';

    public function getSectionDescription(): string
    {
        return 'Signalez, affectez et suivez les incidents et demandes jusqu’à leur clôture.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Incidents', 'description' => 'Sécurité, violence, accident, dégradation ou transport', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => IncidentVieScolaireResource::getUrl()],
            ['label' => 'Réclamations', 'description' => 'Demandes, réponses, délais et historique', 'icon' => 'heroicon-o-chat-bubble-left-right', 'url' => ReclamationResource::getUrl()],
        ];
    }
}
