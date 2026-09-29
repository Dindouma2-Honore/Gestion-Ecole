<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Widgets;

use App\Modules\Communication\Models\FileAttenteNotification;
use App\Modules\Communication\Models\NotificationEnvoyee;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NotificationQueueWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $enAttenteCount = FileAttenteNotification::count();
        $envoyeesCount = NotificationEnvoyee::where('statut', 'envoyee')->count();
        $echecsCount = NotificationEnvoyee::where('statut', 'echec')->count();

        return [
            Stat::make("File d'attente", (string) $enAttenteCount)
                ->description('Notifications prêtes pour envoi')
                ->descriptionIcon('heroicon-o-clock')
                ->color($enAttenteCount > 100 ? 'warning' : 'info'),

            Stat::make('Notifications envoyées', (string) $envoyeesCount)
                ->description('Total transmis avec succès')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Échecs de transmission', (string) $echecsCount)
                ->description('Notifications en échec (à surveiller)')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($echecsCount > 0 ? 'danger' : 'success'),
        ];
    }
}
