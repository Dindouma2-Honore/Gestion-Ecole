<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Pages;

use App\Modules\Logistique\Filament\Resources\AbonnementCantineResource;
use App\Modules\Logistique\Filament\Resources\CircuitTransportResource;
use App\Modules\Logistique\Filament\Resources\DemandeInterventionResource;
use App\Modules\Logistique\Filament\Resources\EquipementResource;
use App\Modules\Logistique\Filament\Resources\EvenementResource;
use App\Modules\Logistique\Filament\Resources\LivreResource;
use App\Modules\Logistique\Filament\Resources\SalleResource;
use App\Modules\Logistique\Filament\Resources\TravauxInfrastructureResource;
use Filament\Pages\Page;

class LogistiqueDashboard extends Page
{
    protected static ?string $slug = 'logistique';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Logistique';

    protected string $view = 'filament.pages.module-placeholder';

    public function getModuleIcon(): string
    {
        return 'heroicon-o-truck';
    }

    public function getModuleDescription(): string
    {
        return 'Gérez les infrastructures, équipements, la maintenance, la bibliothèque, la cantine, le transport scolaire et les événements depuis un espace unique.';
    }

    public function getModuleLinks(): array
    {
        return [
            [
                'label' => 'Salles',
                'description' => 'Infrastructures et disponibilité des salles',
                'icon' => 'heroicon-o-building-office-2',
                'url' => SalleResource::getUrl('index'),
            ],
            [
                'label' => 'Travaux',
                'description' => 'Planification et suivi des travaux',
                'icon' => 'heroicon-o-wrench',
                'url' => TravauxInfrastructureResource::getUrl('index'),
            ],
            [
                'label' => 'Équipements',
                'description' => 'Inventaire du matériel durable et traçable',
                'icon' => 'heroicon-o-computer-desktop',
                'url' => EquipementResource::getUrl('index'),
            ],
            [
                'label' => 'Interventions',
                'description' => 'Maintenance curative et préventive',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'url' => DemandeInterventionResource::getUrl('index'),
            ],
            [
                'label' => 'Bibliothèque',
                'description' => 'Catalogue de livres et emprunts',
                'icon' => 'heroicon-o-book-open',
                'url' => LivreResource::getUrl('index'),
            ],
            [
                'label' => 'Cantine',
                'description' => 'Abonnements et restauration scolaire',
                'icon' => 'heroicon-o-cake',
                'url' => AbonnementCantineResource::getUrl('index'),
            ],
            [
                'label' => 'Transport scolaire',
                'description' => 'Circuits, chauffeurs et présences',
                'icon' => 'heroicon-o-truck',
                'url' => CircuitTransportResource::getUrl('index'),
            ],
            [
                'label' => 'Événements scolaires',
                'description' => 'Organisation, participants et autorisations',
                'icon' => 'heroicon-o-calendar-days',
                'url' => EvenementResource::getUrl('index'),
            ],
        ];
    }
}
