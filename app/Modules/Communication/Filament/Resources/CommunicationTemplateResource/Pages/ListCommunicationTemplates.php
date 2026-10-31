<?php

namespace App\Modules\Communication\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource;
use App\Modules\Communication\Jobs\SyncWhatsAppTemplatesJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListCommunicationTemplates extends ListRecords
{
    protected static string $resource = CommunicationTemplateResource::class;

    public function getSubheading(): ?string
    {
        $query = CommunicationTemplateResource::getEloquentQuery();
        $counts = (clone $query)->selectRaw('meta_status, count(*) as total')->groupBy('meta_status')->pluck('total', 'meta_status');

        return 'WHATSAPP CLOUD API — '.(config('whatsapp.mode') === 'test' ? 'MODE TEST META' : 'MODE PRODUCTION')
            .' · Expéditeur : '.(config('whatsapp.sender') ?: 'Non configuré')
            .' · Phone ID : '.(config('whatsapp.phone_id') ? 'Configuré' : 'Non configuré')
            .' · WABA : '.(config('whatsapp.waba_id') ? 'Configuré' : 'Non configuré')
            .' · Token : '.(config('whatsapp.token') ? 'Configuré' : 'Non configuré')
            .' · '.$counts->sum().' locaux · '.($counts->sum() - ($counts['NOT_SUBMITTED'] ?? 0)).' avec état fournisseur · '.($counts['PENDING'] ?? 0).' en attente · '.($counts['APPROVED'] ?? 0).' approuvés · '.($counts['REJECTED'] ?? 0).' rejetés';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make(), Action::make('syncAll')->label('Synchroniser tous les templates')->requiresConfirmation()
            ->visible(fn () => CommunicationTemplateResource::allowed('sync_all_whatsapp_templates'))
            ->action(function () {
                Gate::authorize('sync_all_whatsapp_templates');
                CommunicationTemplateResource::run(function () {
                    SyncWhatsAppTemplatesJob::dispatch()->onQueue(config('whatsapp.templates.queue', 'whatsapp-templates'));

                    return 'Synchronisation mise en file d’attente.';
                });
            })];
    }
}
