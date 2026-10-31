<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Jobs\SyncWhatsAppTemplatesJob;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Support\WhatsAppTemplateAudit as Audit;
use App\Modules\Socle\Contracts\WhatsAppTestTemplateCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsAppTemplateService
{
    public const FIELDS = ['name', 'code', 'provider_template_name', 'audience', 'module', 'trigger_event',
        'language', 'provider_category', 'header_type', 'header_text', 'header_handle', 'body', 'footer', 'buttons', 'variables_schema'];

    public function catalog(): Collection
    {
        return DB::table('whatsapp_template_metadata')->whereIn('code', WhatsAppTestTemplateCatalog::CODES)->orderBy('code')->get();
    }

    public function create(array $data): CommunicationTemplate
    {
        $this->authorize('create_whatsapp_template');

        return DB::transaction(function () use ($data) {
            $t = CommunicationTemplate::create(array_merge($this->clean($data), [
                'waba_id' => (string) config('whatsapp.waba_id'), 'local_status' => 'DRAFT', 'meta_status' => 'NOT_SUBMITTED',
            ]));
            Audit::record($t, 'template_created');

            return $t;
        });
    }

    public function update(CommunicationTemplate $template, array $data): CommunicationTemplate
    {
        $this->authorize('update_whatsapp_template');

        return DB::transaction(function () use ($template, $data) {
            $t = $this->locked($template);
            if (! $t->editable()) {
                $this->invalid('Dupliquez ce template pour préparer une nouvelle version.');
            }
            $old = Audit::values($t);
            $t->fill($this->clean($data));
            $t->local_status = 'DRAFT';
            $t->save();
            Audit::record($t, 'template_updated', $old);

            return $t;
        });
    }

    public function prepareDocumentExample(CommunicationTemplate $template): void
    {
        $this->authorize('update_whatsapp_template');
        $this->authorize('submit_whatsapp_template');
        $template->refresh();
        $this->account($template);
        if (! $template->editable() || $template->header_type !== 'DOCUMENT') {
            $this->invalid('Un brouillon avec en-tête document est requis.');
        }
        $handle = app(MetaTemplateApiClient::class)->uploadDocumentExample();
        $this->update($template, ['header_handle' => $handle]);
    }

    public function markReady(CommunicationTemplate $template): void
    {
        $this->authorize('update_whatsapp_template');
        DB::transaction(function () use ($template) {
            $t = $this->locked($template);
            if (! $t->editable()) {
                $this->invalid('Ce template ne peut plus être modifié.');
            }
            app(WhatsAppTemplateValidator::class)->validate($t);
            $old = Audit::values($t);
            $t->update(['local_status' => 'READY']);
            Audit::record($t, 'template_marked_ready', $old);
        });
    }

    public function submitToMeta(CommunicationTemplate $template): void
    {
        $this->authorize('submit_whatsapp_template');
        [$t, $payload] = DB::transaction(function () use ($template) {
            $t = $this->locked($template);
            if (! $t->editable() || $t->local_status !== 'READY') {
                $this->invalid('Validez localement un nouveau template avant soumission.');
            }
            $payload = app(WhatsAppTemplateValidator::class)->validate($t);
            $t->update(['submission_state' => 'SENDING', 'meta_status' => 'UNKNOWN', 'submitted_at' => now(),
                'submitted_by' => auth()->id(), 'provider_category_requested' => $payload['category'],
                'content_hash' => app(WhatsAppTemplateValidator::class)->hash($payload['components'])]);
            Audit::record($t, 'template_submission_started');

            return [$t, $payload];
        });
        try {
            $response = app(MetaTemplateApiClient::class)->submit($payload);
            if (! is_string($response['id'] ?? null) && ! is_int($response['id'] ?? null)) {
                throw new \RuntimeException('Identifiant Meta absent.');
            }
            DB::transaction(function () use ($t, $response) {
                $t = $this->locked($t);
                $t->update(['provider_template_id' => (string) $response['id'], 'submission_state' => 'SUBMITTED',
                    // Only a subsequent authoritative reconciliation enables local activation.
                    'meta_status' => 'PENDING', 'provider_response' => $this->safeResponse($response), 'provider_error_code' => null,
                    'provider_category_effective' => $response['category'] ?? null, 'provider_feedback' => null]);
                Audit::record($t, 'template_submitted', [], $this->safeResponse($response), $t->submitted_by);
            });
        } catch (\Throwable $e) {
            $t->update(['submission_state' => 'UNCERTAIN', 'meta_status' => 'UNKNOWN',
                'provider_error_code' => (string) $e->getCode(), 'provider_feedback' => 'Soumission non confirmée. Synchronisez avant toute nouvelle tentative.']);
            Audit::record($t, 'template_submission_failed', [], ['error_code' => (string) $e->getCode()], $t->submitted_by);
            $this->invalid('Soumission non confirmée (code '.$e->getCode().'). Actualisez le statut ; aucune nouvelle soumission automatique.');
        }
    }

    public function syncTemplateStatus(CommunicationTemplate $template, bool $system = false): void
    {
        if (! $system) {
            $this->authorize('sync_whatsapp_template');
        }
        $this->account($template);
        $requestedAt = now()->timestamp;
        $found = false;
        foreach (app(MetaTemplateApiClient::class)->all($template->provider_template_name) as $remote) {
            if (($remote['name'] ?? '') === $template->provider_template_name && ($remote['language'] ?? '') === $template->language) {
                $this->reconcile($template, $remote, $requestedAt);
                $found = true;
            }
        }
        if (! $found) {
            DB::transaction(function () use ($template) {
                $t = $this->locked($template);
                if ($t->meta_status !== 'NOT_SUBMITTED') {
                    $old = Audit::values($t);
                    $t->update(['meta_status' => 'UNKNOWN', 'local_status' => $t->local_status === 'ACTIVE' ? 'DISABLED' : $t->local_status,
                        'last_synced_at' => now(), 'provider_feedback' => 'Template absent du catalogue Meta pour ce WABA et cette langue.']);
                    Audit::record($t, 'template_synced', $old);
                }
            });
        }
    }

    public function syncTemplates(bool $system = false): int
    {
        if (! $system) {
            $this->authorize('sync_all_whatsapp_templates');
        }
        $requestedAt = now()->timestamp;
        $seen = [];
        foreach (app(MetaTemplateApiClient::class)->all() as $remote) {
            if (empty($remote['id']) || empty($remote['name']) || empty($remote['language'])) {
                continue;
            }
            $t = CommunicationTemplate::firstOrCreate(['waba_id' => (string) config('whatsapp.waba_id'),
                'provider_template_name' => $remote['name'], 'language' => $remote['language']], $this->importData($remote));
            $this->reconcile($t, $remote, $requestedAt);
            $seen[] = $t->id;
        }
        // Only after every page succeeds may missing provider records be marked unknown.
        foreach (CommunicationTemplate::where('waba_id', (string) config('whatsapp.waba_id'))->whereNotNull('provider_template_id')->whereNotIn('id', $seen)->get() as $t) {
            DB::transaction(function () use ($t) {
                $t = $this->locked($t);
                $old = Audit::values($t);
                $t->update(['meta_status' => 'UNKNOWN', 'local_status' => 'DISABLED', 'last_synced_at' => now()]);
                Audit::record($t, 'template_synced', $old);
            });
        }

        return count($seen);
    }

    private function reconcile(CommunicationTemplate $template, array $remote, int $requestedAt): void
    {
        DB::transaction(function () use ($template, $remote, $requestedAt) {
            $t = $this->locked($template);
            if ($t->last_webhook_at && $t->last_webhook_at->timestamp >= $requestedAt) {
                return;
            }
            if ($t->provider_template_id && (string) ($remote['id'] ?? '') !== $t->provider_template_id) {
                $this->invalid('Identité Meta différente : dupliquez ou importez la nouvelle version.');
            }
            $old = Audit::values($t);
            $status = $this->status($remote['status'] ?? 'UNKNOWN');
            $components = $remote['components'] ?? [];
            $remoteHash = app(WhatsAppTemplateValidator::class)->hash($components);
            $sameContent = $t->content_hash !== null && hash_equals($t->content_hash, $remoteHash);
            $quality = data_get($remote, 'quality_score.score');
            $t->fill(['provider_template_id' => (string) $remote['id'], 'meta_status' => $status,
                'provider_category_effective' => $remote['category'] ?? null, 'last_synced_at' => now(),
                'provider_components' => array_map(fn ($c) => array_intersect_key($c, array_flip(['type', 'format', 'text', 'buttons'])), $components), 'provider_response' => $this->safeResponse($remote),
                'submission_state' => 'SUBMITTED', 'provider_error_code' => null,
                'provider_feedback' => $sameContent ? null : 'Le contenu local diffère du contenu Meta. Dupliquez le modèle pour une nouvelle version.',
                'rejection_reason' => $status === 'REJECTED' ? $this->safeReason($remote['rejected_reason'] ?? null) : null]);
            if ($status === 'APPROVED' && ! $t->approved_at) {
                $t->approved_at = now();
            }
            if ($status === 'REJECTED' && ! $t->rejected_at) {
                $t->rejected_at = now();
            }
            if ($quality !== null) {
                $t->quality_rating = in_array($quality, ['GREEN', 'YELLOW', 'RED', 'UNKNOWN']) ? $quality : 'UNKNOWN';
                $t->quality_updated_at = now();
            }
            if ($t->local_status === 'ACTIVE' && ($status !== 'APPROVED' || ! $sameContent)) {
                $t->local_status = 'DISABLED';
            }
            $t->save();
            Audit::record($t, 'template_synced', $old, $this->safeResponse($remote));
            if (($old['meta_status'] ?? '') !== $status && in_array($status, ['APPROVED', 'REJECTED'])) {
                Audit::record($t, 'template_'.strtolower($status), $old);
            }
        });
    }

    public function activate(CommunicationTemplate $template): void
    {
        $this->authorize('activate_whatsapp_template');
        DB::transaction(function () use ($template) {
            $t = $this->locked($template);
            app(WhatsAppTemplateResolver::class)->assertApproved($t, false);
            $old = Audit::values($t);
            $t->update(['local_status' => 'ACTIVE']);
            Audit::record($t, 'template_activated', $old);
        });
    }

    public function disable(CommunicationTemplate $template): void
    {
        $this->authorize('disable_whatsapp_template');
        DB::transaction(function () use ($template) {
            $t = $this->locked($template);
            $old = Audit::values($t);
            $t->update(['local_status' => 'DISABLED']);
            Audit::record($t, 'template_disabled', $old);
        });
    }

    public function duplicate(CommunicationTemplate $template): CommunicationTemplate
    {
        $this->authorize('create_whatsapp_template');
        $this->authorize('view_whatsapp_templates');
        $this->account($template);
        $data = $template->only(self::FIELDS);
        $suffix = '_v'.strtolower(Str::random(8));
        $data['code'] = substr($template->code, 0, 130).$suffix;
        $data['provider_template_name'] = substr($template->provider_template_name, 0, 130).$suffix;
        $data['name'] = substr($template->name, 0, 230).' (copie)';
        $data['header_handle'] = null;

        return $this->create($data);
    }

    public function webhook(array $value, int $timestamp): void
    {
        $t = CommunicationTemplate::where('waba_id', (string) config('whatsapp.waba_id'))->where('provider_template_id', (string) ($value['message_template_id'] ?? ''))->first();
        if (! $t || $timestamp <= 0) {
            return;
        }
        DB::transaction(function () use ($t, $value, $timestamp) {
            $t = $this->locked($t);
            if ($t->last_webhook_at && $t->last_webhook_at->timestamp > $timestamp) {
                return;
            }
            $old = Audit::values($t);
            // Restrictive changes block immediately. Approval webhooks cannot reactivate a template;
            // reconciliation fetches current content/status before activation is possible.
            $status = $this->status($value['event'] ?? 'UNKNOWN');
            $t->last_webhook_at = Carbon::createFromTimestamp($timestamp);
            if (in_array($status, ['REJECTED', 'PAUSED', 'DISABLED', 'UNKNOWN', 'PENDING'])) {
                $t->meta_status = $status;
                if ($t->local_status === 'ACTIVE') {
                    $t->local_status = 'DISABLED';
                }
            }
            if ($status === 'APPROVED') {
                $t->meta_status = 'APPROVED';
                $t->approved_at ??= now();
            }
            $t->last_synced_at = null;
            if ($status === 'REJECTED') {
                $t->rejection_reason = $this->safeReason($value['reason'] ?? null);
                $t->rejected_at = now();
            }
            $t->save();
            Audit::record($t, 'template_webhook_received', $old, ['status' => $status]);
        });
        SyncWhatsAppTemplatesJob::dispatch($t->id)->onQueue(config('whatsapp.templates.queue', 'whatsapp-templates'))->delay(now()->addSeconds(2));
    }

    private function importData(array $remote): array
    {
        $components = collect($remote['components'] ?? []);
        $header = $components->firstWhere('type', 'HEADER');
        $body = $components->firstWhere('type', 'BODY');
        $vars = [];
        foreach (['BODY' => $body, 'HEADER' => $header] as $type => $component) {
            preg_match_all('/\{\{([1-9][0-9]*)\}\}/', $component['text'] ?? '', $matches);
            foreach (array_unique($matches[1]) as $position) {
                $vars[] = ['component' => $type, 'position' => (int) $position, 'key' => strtolower($type).'_'.$position,
                    'example' => data_get($component, $type === 'BODY' ? 'example.body_text.0.'.($position - 1) : 'example.header_text.'.($position - 1), 'Exemple '.$position)];
            }
        }

        return ['name' => $remote['name'], 'code' => 'meta_'.$remote['id'], 'audience' => 'ADMINISTRATION', 'module' => 'COMMUNICATION',
            'provider_category' => $remote['category'] ?? 'UTILITY', 'header_type' => $header['format'] ?? 'NONE',
            'header_text' => $header['text'] ?? null, 'body' => $body['text'] ?? '', 'footer' => data_get($components->firstWhere('type', 'FOOTER'), 'text'),
            'buttons' => data_get($components->firstWhere('type', 'BUTTONS'), 'buttons', []), 'variables_schema' => $vars,
            'local_status' => 'DISABLED', 'content_hash' => app(WhatsAppTemplateValidator::class)->hash($remote['components'] ?? [])];
    }

    private function authorize(string $permission): void
    {
        if (auth()->user()?->statut !== 'actif') {
            throw new AuthorizationException;
        }
        Gate::authorize($permission);
    }

    private function clean(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::FIELDS));
        if (isset($data['provider_template_name'])) {
            $data['provider_template_name'] = strtolower(trim($data['provider_template_name']));
        }

        return $data;
    }

    private function locked(CommunicationTemplate $t): CommunicationTemplate
    {
        $t = CommunicationTemplate::whereKey($t->id)->lockForUpdate()->firstOrFail();
        $this->account($t);

        return $t;
    }

    private function account(CommunicationTemplate $t): void
    {
        if ($t->waba_id !== (string) config('whatsapp.waba_id')) {
            $this->invalid('Ce template appartient à un autre WABA.');
        }
    }

    private function status(string $status): string
    {
        return in_array($status, ['PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED']) ? $status : 'UNKNOWN';
    }

    private function safeResponse(array $r): array
    {
        return array_intersect_key($r, array_flip(['id', 'name', 'language', 'status', 'category']));
    }

    private function safeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }
        $reason = preg_replace('/(Bearer\s+\S+|EAA[A-Za-z0-9]+|(?:access_token|token|secret|password)\s*[=:]\s*\S+)/i', '********', $reason);

        return mb_substr(strip_tags($reason), 0, 1500);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['template' => $message]);
    }
}
