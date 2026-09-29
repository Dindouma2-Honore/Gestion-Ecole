<?php

namespace App\Modules\Assiduite\Filament\Widgets;

use App\Modules\Assiduite\Models\Reclamation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReclamationsEnRetardWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $enRetard = Reclamation::where('delai_reponse', '<', now())
            ->whereNotIn('statut', ['resolue', 'cloturee'])
            ->count();

        $ouvertes = Reclamation::whereIn('statut', ['ouverte', 'affectee', 'en_cours'])->count();

        return [
            Stat::make('Réclamations Ouvertes', (string) $ouvertes)
                ->description('En cours de traitement')
                ->color('info')
                ->icon('heroicon-o-inbox'),
            Stat::make('Réclamations en Retard', (string) $enRetard)
                ->description('Délai dépassé')
                ->color($enRetard > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock'),
        ];
    }
}
