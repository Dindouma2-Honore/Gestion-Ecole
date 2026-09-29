<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Widgets;

use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Models\SessionCaisse;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Throwable;

class CaisseOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $sessionOuverte = SessionCaisse::query()->where('statut', 'ouverte')->first();

        $solde = null;
        if ($sessionOuverte) {
            try {
                $solde = app(CaisseServiceContract::class)->getSoldeTheoriqueActuel();
            } catch (Throwable) {
                $solde = null;
            }
        }

        return [
            Stat::make('Session du jour', $sessionOuverte ? 'Ouverte' : 'Fermée')
                ->description($sessionOuverte ? 'Ouverte à '.Carbon::parse($sessionOuverte->created_at)->format('H:i') : 'Aucune session ouverte')
                ->icon('heroicon-o-lock-open')
                ->color($sessionOuverte ? 'success' : 'gray'),
            Stat::make('Solde théorique', $solde !== null ? number_format($solde, 0, ',', ' ').' FCFA' : '—')
                ->icon('heroicon-o-banknotes')
                ->color('primary'),
            Stat::make('Mouvements du jour', $sessionOuverte?->mouvements()->count() ?? 0)
                ->icon('heroicon-o-arrows-right-left')
                ->color('info'),
        ];
    }
}
