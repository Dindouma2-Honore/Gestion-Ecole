<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Widgets;

use App\Modules\Socle\Models\AnneeScolaire;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnneeScolaireOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'A3 — Années scolaires & périodes';

    protected function getStats(): array
    {
        $active = AnneeScolaire::query()->where('statut', AnneeScolaire::STATUT_ACTIVE)->first();

        return [
            Stat::make('Année active', $active?->libelle ?? 'Non définie')
                ->description($active ? 'Contexte de travail actuel' : 'Activation requise')
                ->descriptionIcon($active ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->color($active ? 'success' : 'danger'),
            Stat::make('En préparation', AnneeScolaire::query()->where('statut', AnneeScolaire::STATUT_BROUILLON)->count())
                ->description('Années en brouillon')->descriptionIcon('heroicon-o-pencil-square')->color('warning'),
            Stat::make('Historique', AnneeScolaire::query()->whereIn('statut', [AnneeScolaire::STATUT_CLOTUREE, AnneeScolaire::STATUT_ARCHIVEE])->count())
                ->description('Années clôturées ou archivées')->descriptionIcon('heroicon-o-archive-box')->color('info'),
        ];
    }
}
