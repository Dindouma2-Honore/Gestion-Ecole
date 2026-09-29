<?php

namespace App\Modules\Assiduite\Filament\Widgets;

use App\Modules\Assiduite\Models\DossierSante;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AlertesSanteWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $dossiersAvecAllergies = DossierSante::whereNotNull('allergies')->where('allergies', '!=', '')->count();
        $dossiersMaladiesChroniques = DossierSante::whereNotNull('maladies_chroniques')->where('maladies_chroniques', '!=', '')->count();

        return [
            Stat::make('Élèves avec Allergies', (string) $dossiersAvecAllergies)
                ->description('Allergies signalées')
                ->color('danger')
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Maladies Chroniques', (string) $dossiersMaladiesChroniques)
                ->description('Suivi médical particulier')
                ->color('warning')
                ->icon('heroicon-o-heart'),
        ];
    }
}
