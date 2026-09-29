<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\AmbassadorsDesign;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdministrationOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'A1 — Utilisateurs, rôles & permissions';

    protected function getStats(): array
    {
        return [
            Stat::make('Utilisateurs actifs', User::query()->where('statut', 'actif')->count())
                ->description('Comptes autorisés à accéder à la plateforme')
                ->descriptionIcon('heroicon-m-users')
                ->color(AmbassadorsDesign::ADMINISTRATION_KPI_COLOR),
            Stat::make('Rôles définis', Role::query()->count())
                ->description('Profils fonctionnels disponibles')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color(AmbassadorsDesign::ADMINISTRATION_KPI_COLOR),
            Stat::make('Permissions', Permission::query()->count())
                ->description('Autorisations métier configurées')
                ->descriptionIcon('heroicon-m-key')
                ->color(AmbassadorsDesign::ADMINISTRATION_KPI_COLOR),
            Stat::make('Comptes bloqués', User::query()->whereIn('statut', ['suspendu', 'desactive'])->count())
                ->description('Comptes nécessitant une vérification')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color(AmbassadorsDesign::ADMINISTRATION_KPI_COLOR),
        ];
    }
}
