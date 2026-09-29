<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Widgets;

use App\Modules\Finances\Models\EcheancierPaiement;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\RemiseExoneration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancesOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            Stat::make('Grilles de frais', GrilleFrais::query()->count())->icon('heroicon-o-table-cells')->color('primary'),
            Stat::make('Montant configuré', number_format((float) GrilleFrais::query()->sum('montant'), 0, ',', ' ').' FC')->icon('heroicon-o-banknotes')->color('success'),
            Stat::make('Échéances', EcheancierPaiement::query()->count())->icon('heroicon-o-calendar-days')->color('info'),
            Stat::make('Remises accordées', RemiseExoneration::query()->count())->icon('heroicon-o-receipt-percent')->color('warning'),
        ];
    }
}
