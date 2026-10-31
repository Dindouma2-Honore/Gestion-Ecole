<?php

namespace App\Modules\Communication\Filament\Pages;

use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\WhatsAppCloudApiClient;
use App\Modules\Communication\Services\WhatsAppDispatcher;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use Filament\Pages\Page;

class WhatsAppTest extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $slug = 'communication/whatsapp-test';

    protected static ?string $title = 'WhatsApp — Test Meta';

    protected string $view = 'communication::whatsapp-test';

    public string $recipient = '';

    public string $result = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('whatsapp.view') ?? false;
    }

    public function connection(): void
    {
        abort_unless(auth()->user()?->can('whatsapp.connect'), 403);
        try {
            $this->result = app(WhatsAppCloudApiClient::class)->connection() ? 'Connexion validée' : 'Connexion refusée';
        } catch (\Throwable $e) {
            $this->result = 'Configuration ou connexion Meta indisponible.';
        }
    }

    public function send(bool $simulation = false, bool $pdf = false): void
    {
        abort_unless(auth()->user()?->can($simulation ? 'whatsapp.simulate' : 'whatsapp.send'), 403);
        $this->validate(['recipient' => 'required|string|max:32']);
        $m = app(WhatsAppDispatcher::class)->dispatch($this->recipient, ($pdf ? ['type' => 'fictitious_pdf'] : ['type' => 'template', 'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']]]), simulation: $simulation);
        $this->result = '#'.$m->id.' '.$m->status.' '.$m->reason;
    }

    public function templates()
    {
        abort_unless(static::canAccess(), 403);

        return app(WhatsAppTemplateService::class)->catalog();
    }

    public function messages()
    {
        abort_unless(static::canAccess(), 403);

        return CommunicationMessage::latest()->limit(50)->get();
    }
}
