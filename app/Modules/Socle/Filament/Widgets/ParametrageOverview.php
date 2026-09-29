<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Widgets;

use App\Modules\Socle\Filament\Resources\FormatNumerotationResource;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\JourFerie;
use App\Modules\Socle\Models\Niveau;
use App\Modules\Socle\Models\SeuilValidation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ParametrageOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'A2 — Paramétrage général';

    protected function getStats(): array
    {
        return [
            Stat::make('Niveaux', Niveau::query()->count())
                ->description('Niveaux d’enseignement définis')
                ->descriptionIcon('heroicon-o-academic-cap')
                ->color('primary'),
            Stat::make('Formats de numérotation', FormatNumerotation::query()->count())
                ->description('Types de documents numérotés automatiquement')
                ->descriptionIcon('heroicon-o-hashtag')
                ->color('info')
                ->url(FormatNumerotationResource::getUrl('index')),
            Stat::make('Seuils de validation', SeuilValidation::query()->count())
                ->description('Circuits de validation des dépenses')
                ->descriptionIcon('heroicon-o-scale')
                ->color('warning'),
            Stat::make('Jours fériés', JourFerie::query()->count())
                ->description('Jours fériés configurés')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('gray'),
        ];
    }
}
