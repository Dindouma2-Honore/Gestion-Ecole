<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Exceptions\WhatsAppTemplateNotApprovedException;
use App\Modules\Communication\Models\CommunicationTemplate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudApiClient
{
    public function request(): PendingRequest
    {
        app(WhatsAppEnvironmentGuard::class)->credentials();

        return Http::withToken(config('whatsapp.token'))->baseUrl('https://graph.facebook.com/'.config('whatsapp.version'))->connectTimeout(5)->timeout(config('whatsapp.timeout'))->withoutRedirecting();
    }

    public function connection(): bool
    {
        app(WhatsAppEnvironmentGuard::class)->check((string) (config('whatsapp.allowlist')[0] ?? ''));

        return $this->request()->get('/'.config('whatsapp.phone_id'), ['fields' => 'id'])->successful();
    }

    public function send(string $recipient, array $payload): array
    {
        $recipient = app(WhatsAppEnvironmentGuard::class)->check($recipient);
        if (($payload['type'] ?? '') === 'template' && $payload !== ['type' => 'template', 'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']]]) {
            $template = CommunicationTemplate::where('waba_id', (string) config('whatsapp.waba_id'))
                ->where('provider_template_name', data_get($payload, 'template.name'))
                ->where('language', data_get($payload, 'template.language.code'))->first();
            if (! $template) {
                throw new WhatsAppTemplateNotApprovedException;
            }
            app(WhatsAppTemplateResolver::class)->assertApproved($template);
        }
        $response = $this->request()->post('/'.config('whatsapp.phone_id').'/messages', array_merge($payload, ['messaging_product' => 'whatsapp', 'to' => $recipient]));
        if (! $response->successful()) {
            throw new \RuntimeException('provider_http_'.$response->status());
        }
        $id = $response->json('messages.0.id');
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('provider_missing_message_id');
        }

        return ['id' => $id];
    }
}
