<?php

namespace App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages\Widgets;

use App\Modules\RH\Models\BulletinPaie;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MassePaieWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $bulletins = BulletinPaie::query()->where('statut', 'calcule');
        $total = (float) (clone $bulletins)->sum('net_a_payer');
        $nombre = (clone $bulletins)->count();

        return [
            Stat::make('Masse globale à valider', number_format($total, 0, ',', ' ').' FCFA')
                ->description("{$nombre} bulletin(s) calculé(s)")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
        ];
    }
}
