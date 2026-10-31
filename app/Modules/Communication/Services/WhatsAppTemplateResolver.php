<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Exceptions\WhatsAppTemplateNotApprovedException;
use App\Modules\Communication\Models\CommunicationTemplate;

class WhatsAppTemplateResolver
{
    public function assertApproved(CommunicationTemplate $t, bool $requireActive = true): void
    {
        if (($requireActive && $t->local_status !== 'ACTIVE') || $t->meta_status !== 'APPROVED' || ! $t->provider_template_id
            || $t->waba_id !== (string) config('whatsapp.waba_id')
            || data_get($t->provider_response, 'name') !== $t->provider_template_name
            || data_get($t->provider_response, 'language') !== $t->language || ! $t->last_synced_at
            || $t->last_synced_at->lt(now()->subMinutes(config('whatsapp.templates.max_status_age_minutes', 60)))
            || ! $t->content_hash || ! hash_equals($t->content_hash, app(WhatsAppTemplateValidator::class)->hash($t->provider_components ?? []))) {
            throw new WhatsAppTemplateNotApprovedException;
        }
        // Also detect local content edits outside the workflow.
        $components = [['type' => 'BODY', 'text' => $t->body]];
        if ($t->header_type !== 'NONE') {
            array_unshift($components, array_filter(['type' => 'HEADER', 'format' => $t->header_type, 'text' => $t->header_type === 'TEXT' ? $t->header_text : null], fn ($v) => $v !== null));
        }
        if (filled($t->footer)) {
            $components[] = ['type' => 'FOOTER', 'text' => $t->footer];
        }
        if ($t->buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => array_map(fn ($b) => array_filter($b, fn ($v) => $v !== null && $v !== ''), $t->buttons)];
        }
        if (! hash_equals($t->content_hash, app(WhatsAppTemplateValidator::class)->hash($components))) {
            throw new WhatsAppTemplateNotApprovedException;
        }
    }

    public function resolve(CommunicationTemplate $t, array $variables, ?string $mediaId = null): array
    {
        $this->assertApproved($t);
        $components = [];
        foreach (['HEADER', 'BODY'] as $component) {
            $params = [];
            foreach (collect($t->variables_schema ?? [])->where('component', $component)->sortBy('position') as $v) {
                $value = $variables[$v['key']] ?? null;
                if (! is_string($value) || trim($value) === '') {
                    throw new \RuntimeException('Variable de template manquante.');
                }
                $params[] = ['type' => 'text', 'text' => $value];
            }
            if ($component === 'HEADER' && in_array($t->header_type, ['DOCUMENT', 'IMAGE', 'VIDEO'])) {
                if (! $mediaId) {
                    throw new \RuntimeException('Média requis.');
                }
                $type = strtolower($t->header_type);
                $params = [['type' => $type, $type => ['id' => $mediaId]]];
            }
            if ($params) {
                $components[] = ['type' => strtolower($component), 'parameters' => $params];
            }
        }
        if ($mediaId && ! preg_match('/^[a-zA-Z0-9._-]+$/D', $mediaId)) {
            throw new \RuntimeException('Identifiant média invalide.');
        }
        $template = ['name' => $t->provider_template_name, 'language' => ['code' => $t->language]];
        if ($components) {
            $template['components'] = $components;
        }

        return ['type' => 'template', 'template' => $template];
    }
}
