<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Widgets;

use App\Modules\Finances\Contracts\RecouvrementServiceContract;
use App\Modules\Finances\Models\EcheancierNegocie;
use App\Modules\Finances\Models\RelancePaiement;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

class RecouvrementOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        try {
            $service = app(RecouvrementServiceContract::class);
            $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
            $debiteurs = $service->getListeDebiteurs();
            $taux = $service->getTauxRecouvrement($anneeId);
        } catch (Throwable) {
            $debiteurs = collect();
            $taux = null;
        }

        return [
            Stat::make('Élèves débiteurs', $debiteurs->count())
                ->icon('heroicon-o-exclamation-triangle')
                ->color($debiteurs->isEmpty() ? 'success' : 'danger'),
            Stat::make('Taux de recouvrement', $taux !== null ? number_format($taux, 1, ',', ' ').' %' : '—')
                ->icon('heroicon-o-chart-bar')
                ->color('primary'),
            Stat::make('Relances envoyées', RelancePaiement::query()->count())
                ->icon('heroicon-o-paper-airplane')
                ->color('info'),
            Stat::make('Échéanciers actifs', EcheancierNegocie::query()->whereIn('statut', ['accepte', 'respecte'])->count())
                ->icon('heroicon-o-calendar-days')
                ->color('warning'),
        ];
    }
}
