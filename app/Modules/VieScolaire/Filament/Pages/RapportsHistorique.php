<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

class RapportsHistorique extends VieScolaireSection
{
    protected static ?string $slug = 'vie-scolaire/rapports-historique';

    protected static ?string $title = 'Rapports & historique';

    public function getSectionDescription(): string
    {
        return 'Analysez l’activité du module avec des filtres par période, type et statut.';
    }

    public function getSectionLinks(): array
    {
        return [];
    }
}
