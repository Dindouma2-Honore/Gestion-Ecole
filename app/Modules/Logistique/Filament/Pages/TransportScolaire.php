<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Pages;

use App\Modules\Logistique\Filament\Resources\CircuitTransportResource;
use App\Modules\Logistique\Filament\Resources\VehiculeResource;

class TransportScolaire extends LogistiqueSection
{
    protected static ?string $slug = 'logistique/transport-scolaire';

    protected static ?string $title = 'Transport scolaire';

    public function getSectionDescription(): string
    {
        return 'Organisez les circuits, les véhicules, les élèves transportés et l’exploitation quotidienne.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Circuits', 'description' => 'Chauffeurs, accompagnateurs et arrêts', 'icon' => 'heroicon-o-map', 'url' => CircuitTransportResource::getUrl()],
            ['label' => 'Véhicules', 'description' => 'Capacité, disponibilité et responsables', 'icon' => 'heroicon-o-truck', 'url' => VehiculeResource::getUrl()],
        ];
    }
}
