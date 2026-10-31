<?php

namespace App\Modules\Communication\Filament\Widgets;

use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource;
use App\Modules\Communication\Models\CommunicationTemplate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WhatsAppTemplateAlerts extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return CommunicationTemplateResource::canViewAny();
    }

    protected function getStats(): array
    {
        $n = CommunicationTemplate::where('waba_id', (string) config('whatsapp.waba_id'))->whereIn('meta_status', ['REJECTED', 'PAUSED', 'DISABLED'])->count();

        return [Stat::make('Templates nécessitant une intervention', $n)->description('Rejetés, suspendus ou désactivés par Meta')->color($n ? 'warning' : 'gray')->url(CommunicationTemplateResource::getUrl())];
    }
}
