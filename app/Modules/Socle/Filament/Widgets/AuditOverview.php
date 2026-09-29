<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Widgets;

use App\Modules\Socle\Models\ActivityLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AuditOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'A4 — Audit & traçabilité';

    protected function getStats(): array
    {
        return [
            Stat::make('Événements journalisés', ActivityLog::query()->count())
                ->description('Historique total des actions')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('primary'),
            Stat::make('Actions (30 derniers jours)', ActivityLog::query()->where('created_at', '>=', now()->subDays(30))->count())
                ->description('Activité récente')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),
            Stat::make('Consultations sensibles', ActivityLog::query()->where('log_name', 'consultation')->count())
                ->description('Accès à des données sensibles tracés')
                ->descriptionIcon('heroicon-o-eye')
                ->color('warning'),
        ];
    }
}
