<?php

namespace App\Modules\Communication\Services;

class WhatsAppMediaService
{
    public function uploadPdf(string $recipient, string $pdf, string $filename): string
    {
        app(WhatsAppEnvironmentGuard::class)->check($recipient);
        $response = app(WhatsAppCloudApiClient::class)->request()
            ->attach('file', $pdf, $filename, ['Content-Type' => 'application/pdf'])
            ->post('/'.config('whatsapp.phone_id').'/media', ['messaging_product' => 'whatsapp', 'type' => 'application/pdf']);
        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new \RuntimeException('media_upload_failed');
        }

        return $response->json('id');
    }

    /** Only generated fictitious PDF bytes can enter this test path. */
    public function uploadFictitiousPdf(string $recipient): string
    {
        app(WhatsAppEnvironmentGuard::class)->check($recipient);
        $pdf = app('dompdf.wrapper')->loadHTML('<h1>TEST META — DOCUMENT FICTIF</h1><p>Aucune donnée élève. Bulletin de démonstration.</p>')->output();
        if (! str_starts_with($pdf, '%PDF-')) {
            throw new \RuntimeException('invalid_pdf');
        }
        $response = app(WhatsAppCloudApiClient::class)->request()->attach('file', $pdf, 'test-meta.pdf', ['Content-Type' => 'application/pdf'])->post('/'.config('whatsapp.phone_id').'/media', ['messaging_product' => 'whatsapp', 'type' => 'application/pdf']);
        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new \RuntimeException('media_upload_failed');
        }

        return $response->json('id');
    }
}
