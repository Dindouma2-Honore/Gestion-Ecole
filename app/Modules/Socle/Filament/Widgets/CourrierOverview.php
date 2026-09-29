<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Widgets;

use App\Modules\Socle\Models\Courrier;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CourrierOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'A6 — Courriers';

    protected function getStats(): array
    {
        return [
            Stat::make('En cours', Courrier::query()->whereNotIn('statut', ['repondu', 'archive'])->count())
                ->description('Non finalisés')
                ->descriptionIcon('heroicon-o-envelope-open')
                ->color('info'),
            Stat::make('En retard', Courrier::query()
                ->whereNotNull('date_limite_reponse')
                ->where('date_limite_reponse', '<', now())
                ->whereNotIn('statut', ['repondu', 'archive'])
                ->count())
                ->description('Échéance de réponse dépassée')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            Stat::make('Archivés', Courrier::query()->where('statut', 'archive')->count())
                ->description('Traitement terminé')
                ->descriptionIcon('heroicon-o-archive-box')
                ->color('gray'),
        ];
    }
}
