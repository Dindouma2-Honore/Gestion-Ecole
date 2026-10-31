<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

use App\Modules\VieScolaire\Filament\Resources\VisiteurResource;

class Visiteurs extends VieScolaireSection
{
    protected static ?string $slug = 'vie-scolaire/visiteurs';

    protected static ?string $title = 'Visiteurs';

    public function getSectionDescription(): string
    {
        return 'Registre de sécurité des entrées, badges et sorties.';
    }

    public function getSectionLinks(): array
    {
        return [['label' => 'Registre des visiteurs', 'description' => 'Présents actuellement, nouvelle visite et historique', 'icon' => 'heroicon-o-identification', 'url' => VisiteurResource::getUrl()]];
    }
}
