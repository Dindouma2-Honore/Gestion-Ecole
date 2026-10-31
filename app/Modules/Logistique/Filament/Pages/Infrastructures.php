<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Pages;

use App\Modules\Logistique\Filament\Resources\SalleResource;
use App\Modules\Logistique\Filament\Resources\TravauxInfrastructureResource;

class Infrastructures extends LogistiqueSection
{
    protected static ?string $slug = 'logistique/infrastructures';

    protected static ?string $title = 'Infrastructures';

    public function getSectionDescription(): string
    {
        return 'Gérez les espaces physiques, leur disponibilité et les travaux associés.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Espaces', 'description' => 'Classes, laboratoires, bureaux et espaces communs', 'icon' => 'heroicon-o-building-office-2', 'url' => SalleResource::getUrl()],
            ['label' => 'Travaux', 'description' => 'Planification, avancement et coûts des travaux', 'icon' => 'heroicon-o-wrench', 'url' => TravauxInfrastructureResource::getUrl()],
        ];
    }
}
