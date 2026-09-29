<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Widgets;

use App\Modules\RH\Models\Conge;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RHOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            Stat::make('Employés actifs', Employe::query()->where('statut', 'actif')->count())->icon('heroicon-o-user-group')->color('primary'),
            Stat::make('Enseignants', Enseignant::query()->count())->icon('heroicon-o-academic-cap')->color('info'),
            Stat::make('Contrats actifs', Contrat::query()->where('statut', 'actif')->count())->icon('heroicon-o-document-text')->color('success'),
            Stat::make('Congés à traiter', Conge::query()->where('statut', 'demande')->count())->icon('heroicon-o-calendar-days')->color('warning'),
        ];
    }
}
