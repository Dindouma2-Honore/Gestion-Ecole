<?php

namespace App\Modules\Communication\Services;

use Illuminate\Http\Client\Response;

class MetaTemplateApiClient
{
    private function request()
    {
        if (! config('whatsapp.enabled') || ! in_array(config('whatsapp.mode'), ['test', 'production'], true)) {
            throw new \RuntimeException('Gestion Meta désactivée.');
        }
        if (! ctype_digit((string) config('whatsapp.waba_id'))) {
            throw new \RuntimeException('WABA non configuré.');
        }

        return app(WhatsAppCloudApiClient::class)->request();
    }

    public function submit(array $payload): array
    {
        return $this->decode($this->request()->post('/'.config('whatsapp.waba_id').'/message_templates', $payload));
    }

    public function uploadDocumentExample(): string
    {
        $pdf = app('dompdf.wrapper')->loadHTML('<h1>Ambassadors Educational Complex</h1><p>BULLETIN FICTIF — EXEMPLE META</p><p>Élève : Paul Exemple. Classe : 6e A. Aucune donnée réelle.</p>')->output();
        if (! str_starts_with($pdf, '%PDF-') || strlen($pdf) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Exemple PDF invalide.');
        }
        $session = $this->decode($this->request()->post('/app/uploads?'.http_build_query([
            'file_length' => strlen($pdf), 'file_type' => 'application/pdf', 'file_name' => 'bulletin-exemple.pdf',
        ])));
        $id = $session['id'] ?? '';
        if (! is_string($id) || ! preg_match('/^upload:[A-Za-z0-9=_:-]+(?:\?sig=[A-Za-z0-9_-]+)?$/D', $id)) {
            throw new \RuntimeException('Session de téléversement Meta invalide.');
        }
        $uploaded = $this->decode($this->request()->withHeaders(['file_offset' => '0'])->withBody($pdf, 'application/pdf')->post('/'.$id));
        if (! is_string($uploaded['h'] ?? null) || $uploaded['h'] === '') {
            throw new \RuntimeException('Exemple média non confirmé par Meta.');
        }

        return $uploaded['h'];
    }

    public function page(?string $after = null, ?string $name = null): array
    {
        return $this->decode($this->request()->get('/'.config('whatsapp.waba_id').'/message_templates', array_filter([
            'fields' => 'id,name,language,status,category,components,quality_score,rejected_reason',
            'limit' => 100, 'after' => $after, 'name' => $name,
        ], fn ($v) => $v !== null)));
    }

    public function all(?string $name = null): \Generator
    {
        $after = null;
        $seen = [];
        for ($page = 0; $page < 100; $page++) {
            $data = $this->page($after, $name);
            if (! is_array($data['data'] ?? null)) {
                throw new \RuntimeException('Réponse Meta invalide.');
            }
            foreach ($data['data'] as $template) {
                yield $template;
            }
            if (empty($data['paging']['next'])) {
                return;
            }
            $after = $data['paging']['cursors']['after'] ?? null;
            // Follow cursors on our fixed host; never follow provider URLs containing tokens.
            if (! is_string($after) || isset($seen[$after])) {
                throw new \RuntimeException('Pagination Meta invalide.');
            }
            $seen[$after] = true;
        }
        throw new \RuntimeException('Limite de synchronisation atteinte.');
    }

    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            $code = $response->json('error.code');
            throw new \RuntimeException('Meta a refusé la requête (HTTP '.$response->status().', code '.(is_numeric($code) ? (int) $code : 'inconnu').').', is_numeric($code) ? (int) $code : 0);
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException('Réponse Meta invalide.');
        }

        return $data;
    }
}
