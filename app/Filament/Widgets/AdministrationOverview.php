<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Socle\Filament\Resources\UserResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

class AdministrationOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'A1 — Utilisateurs & permissions';

    protected function getStats(): array
    {
        return [
            Stat::make('Utilisateurs actifs', UserResource::getEloquentQuery()->where('statut', 'actif')->count())
                ->description('Comptes autorisés à accéder à la plateforme')
                ->descriptionIcon('heroicon-m-users')
                ->color(AmbassadorsDesign::ADMINISTRATION_KPI_COLOR),
            Stat::make('Comptes supprimés', Activity::where('log_name', 'utilisateurs')->where('event', 'deleted')->count())
                ->description('Comptes retirés de la plateforme')
                ->descriptionIcon('heroicon-m-trash')
                ->color(AmbassadorsDesign::ADMINISTRATION_KPI_COLOR),
            Stat::make('Permissions', Permission::query()->count())
                ->description('Autorisations métier configurées')
                ->descriptionIcon('heroicon-m-key')
                ->color(AmbassadorsDesign::ADMINISTRATION_KPI_COLOR),
        ];
    }
}
