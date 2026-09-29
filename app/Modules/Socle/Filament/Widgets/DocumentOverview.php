<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Widgets;

use App\Modules\Socle\Models\Document;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DocumentOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'A5 — Gestion documentaire';

    protected function getStats(): array
    {
        return [
            Stat::make('Documents', Document::query()->count())
                ->description('Toutes catégories confondues')
                ->descriptionIcon('heroicon-o-document')
                ->color('primary'),
            Stat::make('Expirant sous 30 jours', Document::query()
                ->whereNotNull('date_expiration')
                ->whereBetween('date_expiration', [now(), now()->addDays(30)])
                ->count())
                ->description('Renouvellement à anticiper')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning'),
            Stat::make('Restreints', Document::query()->where('niveau_confidentialite', 'restreint')->count())
                ->description('Accès limité Fondateur/Directeur')
                ->descriptionIcon('heroicon-o-lock-closed')
                ->color('danger'),
        ];
    }
}
