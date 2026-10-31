<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Pages;

use App\Modules\Logistique\Models\DemandeIntervention;
use App\Modules\Logistique\Models\Emprunt;
use App\Modules\Logistique\Models\Equipement;
use App\Modules\Logistique\Models\PlanMaintenancePreventive;
use App\Modules\Logistique\Models\Salle;
use App\Modules\Logistique\Models\Vehicule;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class LogistiqueDashboard extends Page
{
    protected static ?string $slug = 'logistique';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Logistique';

    protected string $view = 'logistique::filament.pages.dashboard';

    /** @return array<int, array{label:string,value:int,color:string,icon:string}> */
    public function getIndicators(): array
    {
        return [
            ['label' => 'Espaces indisponibles', 'value' => Salle::query()->where(fn ($q) => $q->where('disponible', false)->orWhere('etat', 'hors_service'))->count(), 'color' => 'danger', 'icon' => 'heroicon-o-building-office-2'],
            ['label' => 'Équipements en panne', 'value' => Equipement::query()->whereIn('etat', ['a_reparer', 'hors_service'])->count(), 'color' => 'warning', 'icon' => 'heroicon-o-computer-desktop'],
            ['label' => 'Interventions ouvertes', 'value' => DemandeIntervention::query()->whereNotIn('statut', ['terminee'])->count(), 'color' => 'primary', 'icon' => 'heroicon-o-wrench-screwdriver'],
            ['label' => 'Maintenances en retard', 'value' => PlanMaintenancePreventive::query()->where('actif', true)->whereDate('prochaine_echeance', '<', today())->count(), 'color' => 'danger', 'icon' => 'heroicon-o-clock'],
            ['label' => 'Véhicules indisponibles', 'value' => Vehicule::query()->whereIn('etat', ['en_maintenance', 'hors_service'])->count(), 'color' => 'warning', 'icon' => 'heroicon-o-truck'],
            ['label' => 'Emprunts en retard', 'value' => Emprunt::query()->whereNull('date_retour_reelle')->whereDate('date_retour_prevue', '<', today())->count(), 'color' => 'danger', 'icon' => 'heroicon-o-book-open'],
        ];
    }

    public function getPriorityActions(): Collection
    {
        return DemandeIntervention::query()
            ->with(['equipement', 'salle'])
            ->whereNotIn('statut', ['terminee'])
            ->orderByRaw("CASE priorite WHEN 'urgente' THEN 1 WHEN 'haute' THEN 2 WHEN 'normale' THEN 3 ELSE 4 END")
            ->limit(6)->get();
    }

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
            ['label' => 'Infrastructures', 'description' => 'Espaces et travaux', 'icon' => 'heroicon-o-building-office-2', 'url' => Infrastructures::getUrl()],
            ['label' => 'Équipements & maintenance', 'description' => 'Inventaire et interventions', 'icon' => 'heroicon-o-wrench-screwdriver', 'url' => EquipementsMaintenance::getUrl()],
            ['label' => 'Transport scolaire', 'description' => 'Circuits et exploitation', 'icon' => 'heroicon-o-truck', 'url' => TransportScolaire::getUrl()],
            ['label' => 'Cantine', 'description' => 'Abonnements et service', 'icon' => 'heroicon-o-cake', 'url' => Cantine::getUrl()],
            ['label' => 'Bibliothèque', 'description' => 'Catalogue et emprunts', 'icon' => 'heroicon-o-book-open', 'url' => Bibliotheque::getUrl()],
        ];
    }
}
