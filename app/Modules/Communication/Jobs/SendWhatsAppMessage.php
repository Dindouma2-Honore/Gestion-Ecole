<?php

namespace App\Modules\Communication\Jobs;

use App\Models\User;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Services\StaffAccountWhatsApp;
use App\Modules\Communication\Services\WhatsAppCloudApiClient;
use App\Modules\Communication\Services\WhatsAppEnvironmentGuard;
use App\Modules\Communication\Services\WhatsAppMediaService;
use App\Modules\Communication\Services\WhatsAppStatusService;
use App\Modules\Communication\Services\WhatsAppTemplateResolver;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Services\BulletinPaiePdfService;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppEnvironmentGuard $guard, WhatsAppCloudApiClient $client): void
    {
        $m = CommunicationMessage::findOrFail($this->messageId);
        if ($m->status !== 'queued') {
            return;
        }
        try {
            if ($m->mode !== config('whatsapp.mode') || $m->phone_id !== config('whatsapp.phone_id')) {
                throw new \RuntimeException('configuration_changed');
            }
            $guard->check($m->actual_recipient);
            $guard->credentials();
            if (($m->payload['type'] ?? null) === 'staff_account_access') {
                app(StaffAccountWhatsApp::class)->assertReady($m->payload, $m->actual_recipient);
            }
            if ($m->communication_template_id) {
                $template = CommunicationTemplate::findOrFail($m->communication_template_id);
                app(WhatsAppTemplateResolver::class)->assertApproved($template);
                if (! hash_equals((string) $template->content_hash, (string) $m->template_content_hash)) {
                    throw new \RuntimeException('template_changed');
                }
                $actor = User::find($m->initiated_by);
                if (! $actor || $actor->statut !== 'actif' || ! $actor->can('test_whatsapp_template')) {
                    throw new \RuntimeException('permission_revoked');
                }
            }
        } catch (\RuntimeException $e) {
            $m->update(['status' => 'blocked', 'reason' => $e->getMessage()]);

            return;
        }
        // Claim atomically. Never automatically retry an ambiguous network send.
        if (! CommunicationMessage::whereKey($m->id)->where('status', 'queued')->update(['status' => 'sending'])) {
            return;
        }
        try {
            $payload = $m->payload;
            if ($m->communication_template_id && ($payload['type'] ?? null) === 'approved_template_test') {
                $mediaId = $payload['media_id'] ?? null;
                if ($template->header_type === 'DOCUMENT' && ! $mediaId) {
                    $mediaId = app(WhatsAppMediaService::class)->uploadFictitiousPdf($m->actual_recipient);
                }
                $values = collect($template->variables_schema ?? [])->pluck('example', 'key')->all();
                $payload = app(WhatsAppTemplateResolver::class)->resolve($template, $values, $mediaId);
            } elseif (($payload['type'] ?? null) === 'staff_account_access') {
                $payload = app(StaffAccountWhatsApp::class)->payload($payload, $m->actual_recipient);
            } elseif ($payload === ['type' => 'fictitious_pdf']) {
                $mediaId = app(WhatsAppMediaService::class)->uploadFictitiousPdf($m->actual_recipient);
                $payload = ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => 'test-meta.pdf', 'caption' => 'TEST META — document fictif']];
            } elseif (($payload['type'] ?? null) === 'payroll_bulletin') {
                $bulletin = BulletinPaie::with(['employe', 'contrat', 'lignes'])->findOrFail((int) $payload['bulletin_id']);
                $pdf = app(BulletinPaiePdfService::class)->mefientPdf($bulletin)->output();
                $mediaId = app(WhatsAppMediaService::class)->uploadPdf($m->actual_recipient, $pdf, 'bulletin-paie-'.$bulletin->mois.'-'.$bulletin->annee.'.pdf');
                $payload = ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => 'bulletin-paie-'.$bulletin->mois.'-'.$bulletin->annee.'.pdf', 'caption' => 'Votre bulletin de paie est disponible.']];
            } elseif (($payload['type'] ?? null) === 'inscription_invoice') {
                $facture = FacturePreinscription::with('lignes')->findOrFail((int) $payload['facture_id']);
                $annee = app(AnneeScolaireServiceContract::class)->getAnneeScolaire($facture->annee_scolaire_id);
                $pdf = Pdf::loadView('finances::pdf.facture-provisoire', compact('facture', 'annee'))->setPaper('a4')->output();
                $mediaId = app(WhatsAppMediaService::class)->uploadPdf($m->actual_recipient, $pdf, 'facture-inscription-'.$facture->reference.'.pdf');
                $payload = ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => 'facture-inscription-'.$facture->reference.'.pdf', 'caption' => 'Votre facture d’inscription est disponible.']];
            } elseif (($payload['type'] ?? null) === 'school_payment_receipt') {
                $recu = app(PaiementServiceContract::class)->getRecuPdf((int) $payload['paiement_id']);
                $mediaId = app(WhatsAppMediaService::class)->uploadPdf($m->actual_recipient, $recu['contenu'], $recu['nom']);
                $payload = ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $recu['nom'], 'caption' => 'Votre reçu de paiement scolaire est disponible.']];
            } elseif ($payload !== ['type' => 'template', 'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']]]) {
                $m->update(['status' => 'blocked', 'reason' => 'test_payload_only']);

                return;
            }
            $result = $client->send($m->actual_recipient, $payload);
            $m->update(['status' => 'accepted', 'provider_message_id' => $result['id'], 'provider_response' => $result]);
            app(WhatsAppStatusService::class)->reconcile($result['id']);
        } catch (\Throwable $e) {
            $m->update(['status' => 'unknown', 'reason' => 'provider_result_uncertain']);
        }
    }
}
