<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Widgets;

use App\Modules\Socle\Models\Decision;
use App\Modules\Socle\Models\Reunion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReunionOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'A8 — Réunions & décisions';

    protected function getStats(): array
    {
        return [
            Stat::make('À venir', Reunion::query()
                ->where('date_heure', '>=', now())
                ->whereIn('statut', ['planifiee', 'en_cours'])
                ->count())
                ->description('Planifiées ou en cours')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info'),
            Stat::make('Décisions en suivi', Decision::query()
                ->whereHas('tache', fn ($query) => $query->whereNotIn('statut', ['cloturee']))
                ->count())
                ->description('Tâches liées non clôturées')
                ->descriptionIcon('heroicon-o-clipboard-document-check')
                ->color('warning'),
            Stat::make('Clôturées', Reunion::query()->where('statut', 'terminee')->count())
                ->description('Comptes rendus enregistrés')
                ->descriptionIcon('heroicon-o-flag')
                ->color('success'),
        ];
    }
}
