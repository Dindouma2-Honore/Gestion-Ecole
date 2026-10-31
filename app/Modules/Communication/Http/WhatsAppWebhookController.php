<?php

namespace App\Modules\Communication\Http;

use App\Modules\Communication\Jobs\SyncWhatsAppTemplatesJob;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Services\WhatsAppStatusService;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use Illuminate\Http\Request;

class WhatsAppWebhookController
{
    public function verify(Request $request)
    {
        $token = (string) config('whatsapp.verify_token');
        abort_unless($token !== '' && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request)
    {
        $secret = (string) config('whatsapp.app_secret');
        abort_unless($secret !== '' && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), (string) $request->header('X-Hub-Signature-256')), 403);
        foreach ($request->input('entry', []) as $entry) {
            if ((string) ($entry['id'] ?? '') !== (string) config('whatsapp.waba_id')) {
                continue;
            }
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') === 'message_template_status_update') {
                    app(WhatsAppTemplateService::class)->webhook($change['value'] ?? [], (int) ($entry['time'] ?? 0));

                    continue;
                }
                if (($change['field'] ?? '') === 'message_template_quality_update') {
                    $template = CommunicationTemplate::where('waba_id', (string) config('whatsapp.waba_id'))->where('provider_template_id', (string) data_get($change, 'value.message_template_id'))->first();
                    if ($template) {
                        SyncWhatsAppTemplatesJob::dispatch($template->id)->onQueue(config('whatsapp.templates.queue', 'whatsapp-templates'));
                    }

                    continue;
                }
                if ((string) data_get($change, 'value.metadata.phone_number_id') !== (string) config('whatsapp.phone_id')) {
                    continue;
                }
                foreach (data_get($change, 'value.statuses', []) as $status) {
                    app(WhatsAppStatusService::class)->receive((string) ($status['id'] ?? ''), (string) ($status['status'] ?? ''));
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
