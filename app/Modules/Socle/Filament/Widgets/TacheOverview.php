<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Widgets;

use App\Modules\Socle\Models\Tache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TacheOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'A7 — Tâches & validations';

    protected function getStats(): array
    {
        return [
            Stat::make('Actives', Tache::query()->whereNotIn('statut', ['cloturee'])->count())
                ->description('En cours de traitement')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('info'),
            Stat::make('En attente de validation', Tache::query()->where('statut', 'en_attente_validation')->count())
                ->description('Circuit de validation en cours')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('En retard', Tache::query()
                ->where('echeance', '<', now())
                ->whereNotIn('statut', ['validee', 'cloturee'])
                ->count())
                ->description('Échéance dépassée')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
