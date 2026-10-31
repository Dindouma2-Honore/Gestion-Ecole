<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Pages;

use App\Modules\Logistique\Filament\Resources\CategorieMaterielResource;
use App\Modules\Logistique\Filament\Resources\DemandeInterventionResource;
use App\Modules\Logistique\Filament\Resources\EquipementResource;
use App\Modules\Logistique\Filament\Resources\PlanMaintenancePreventiveResource;

class EquipementsMaintenance extends LogistiqueSection
{
    protected static ?string $slug = 'logistique/equipements-maintenance';

    protected static ?string $title = 'Équipements & maintenance';

    public function getSectionDescription(): string
    {
        return 'Pilotez l’inventaire, les mouvements et la maintenance corrective ou préventive.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Inventaire', 'description' => 'Équipements durables, localisation et responsabilité', 'icon' => 'heroicon-o-computer-desktop', 'url' => EquipementResource::getUrl()],
            ['label' => 'Catégories de matériel', 'description' => 'Catalogue : codes, natures durable ou consommable', 'icon' => 'heroicon-o-tag', 'url' => CategorieMaterielResource::getUrl()],
            ['label' => 'Interventions', 'description' => 'Signalements, diagnostics et réparations', 'icon' => 'heroicon-o-wrench-screwdriver', 'url' => DemandeInterventionResource::getUrl()],
            ['label' => 'Maintenance préventive', 'description' => 'Périodicités, échéances et alertes', 'icon' => 'heroicon-o-clock', 'url' => PlanMaintenancePreventiveResource::getUrl()],
        ];
    }
}
