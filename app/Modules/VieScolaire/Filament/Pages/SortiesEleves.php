<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

use App\Modules\VieScolaire\Filament\Resources\SortieEleveResource;

class SortiesEleves extends VieScolaireSection
{
    protected static ?string $slug = 'vie-scolaire/sorties';

    protected static ?string $title = 'Sorties des élèves';

    public function getSectionDescription(): string
    {
        return 'Contrôlez les sorties normales et exceptionnelles et conservez leur historique.';
    }

    public function getSectionLinks(): array
    {
        return [['label' => 'Sorties du jour & historique', 'description' => 'Élève, accompagnateur, autorisation et heure de sortie', 'icon' => 'heroicon-o-arrow-right-on-rectangle', 'url' => SortieEleveResource::getUrl()]];
    }
}
