<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Jobs\SendWhatsAppMessage;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Support\WhatsAppTemplateAudit;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\Paiement;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WhatsAppDispatcher
{
    public function dispatchBulletin(BulletinPaie $bulletin): CommunicationMessage
    {
        $bulletin->loadMissing('employe');

        return $this->dispatch(
            (string) $bulletin->employe?->telephone,
            ['type' => 'payroll_bulletin', 'bulletin_id' => $bulletin->getKey()],
            'bulletin-paie:'.$bulletin->getKey(),
        );
    }

    public function dispatchInscriptionFacture(FacturePreinscription $facture): CommunicationMessage
    {
        return $this->dispatch(
            (string) $facture->parent_telephone,
            ['type' => 'inscription_invoice', 'facture_id' => $facture->getKey()],
            'facture-inscription:'.$facture->getKey(),
        );
    }

    public function dispatchPaiementRecu(Paiement $paiement): CommunicationMessage
    {
        $telephone = null;
        try {
            $eleveData = app(EleveServiceInterface::class)->getEleve($paiement->eleve_id);
            $telephone = $eleveData['parent_telephone'] ?? null;
        } catch (\Throwable) {
        }

        return $this->dispatch(
            (string) $telephone,
            ['type' => 'school_payment_receipt', 'paiement_id' => $paiement->getKey()],
            'recu-paiement:'.$paiement->getKey(),
        );
    }

    public function dispatch(string $recipient, array $payload, ?string $key = null, bool $simulation = false): CommunicationMessage
    {
        $message = CommunicationMessage::firstOrCreate(['idempotency_key' => $key ?? (string) Str::uuid()], [
            'intended_recipient' => $recipient, 'mode' => config('whatsapp.mode'), 'is_test' => config('whatsapp.mode') === 'test',
            'phone_id' => config('whatsapp.phone_id'), 'status' => 'pending', 'payload' => $payload,
        ]);
        if (! $message->wasRecentlyCreated) {
            return $message;
        }
        try {
            if (($payload['type'] ?? null) === 'staff_account_access') {
                app(StaffAccountWhatsApp::class)->assertReady($payload, $recipient);
            }
            $actual = app(WhatsAppEnvironmentGuard::class)->check($recipient, $simulation);
            $message->update(['actual_recipient' => $actual]);
            if ($simulation) {
                $message->update(['status' => 'simulated']);

                return $message;
            }
            if (config('whatsapp.mode') === 'test'
                && ($payload['type'] ?? null) !== 'staff_account_access'
                && ($payload['type'] ?? null) !== 'payroll_bulletin'
                && ($payload['type'] ?? null) !== 'inscription_invoice'
                && ($payload['type'] ?? null) !== 'school_payment_receipt'
                && $payload !== ['type' => 'template', 'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']]]
                && $payload !== ['type' => 'fictitious_pdf']) {
                throw new \RuntimeException('test_payload_only');
            }
            app(WhatsAppEnvironmentGuard::class)->credentials();
            if (config('queue.default') === 'sync') {
                throw new \RuntimeException('async_queue_required');
            }
            $message->update(['status' => 'queued']);
            SendWhatsAppMessage::dispatch($message->id)->onQueue('notifications')->afterCommit();
        } catch (\RuntimeException $e) {
            $reason = preg_match('/^[a-z_]+$/D', $e->getMessage()) ? $e->getMessage() : 'dispatch_unavailable';
            $message->update(['status' => 'blocked', 'reason' => $reason]);
        }

        return $message;
    }

    public function dispatchTemplate(CommunicationTemplate $template, string $recipient, ?string $mediaId = null): CommunicationMessage
    {
        Gate::authorize('test_whatsapp_template');
        $template->refresh();
        app(WhatsAppTemplateResolver::class)->assertApproved($template);
        if (config('whatsapp.mode') === 'production') {
            throw new \RuntimeException('test_forbidden_in_production');
        }
        $actual = app(WhatsAppEnvironmentGuard::class)->check($recipient);
        app(WhatsAppEnvironmentGuard::class)->credentials();
        if (config('queue.default') === 'sync') {
            throw new \RuntimeException('Une queue asynchrone est requise.');
        }
        if (in_array($template->header_type, ['IMAGE', 'VIDEO']) && blank($mediaId)) {
            throw new \RuntimeException('Un identifiant média Meta est requis pour tester ce format.');
        }
        if ($mediaId && ! preg_match('/^[a-zA-Z0-9._-]+$/D', $mediaId)) {
            throw new \RuntimeException('Identifiant média invalide.');
        }

        return DB::transaction(function () use ($template, $recipient, $actual, $mediaId) {
            $message = CommunicationMessage::create([
                'idempotency_key' => (string) Str::uuid(), 'intended_recipient' => $recipient, 'actual_recipient' => $actual,
                'mode' => 'test', 'is_test' => true, 'phone_id' => config('whatsapp.phone_id'), 'status' => 'queued',
                'payload' => ['type' => 'approved_template_test', 'media_id' => $mediaId], 'communication_template_id' => $template->id,
                'template_content_hash' => $template->content_hash, 'initiated_by' => auth()->id(),
            ]);
            WhatsAppTemplateAudit::record($template, 'template_tested', [], ['message_id' => (string) $message->id, 'status' => 'queued']);
            SendWhatsAppMessage::dispatch($message->id)->onQueue(config('whatsapp.templates.queue', 'whatsapp-templates'))->afterCommit();

            return $message;
        });
    }
}
