<?php

namespace App\Modules\Communication\Support;

use App\Modules\Communication\Models\CommunicationTemplate;

class WhatsAppTemplateAudit
{
    public static function values(CommunicationTemplate $template): array
    {
        return $template->only(['name', 'code', 'provider_template_name', 'language', 'audience', 'module',
            'trigger_event', 'body', 'header_type', 'header_text', 'footer', 'buttons', 'variables_schema',
            'local_status', 'meta_status', 'provider_category', 'provider_category_effective',
            'provider_template_id', 'content_hash', 'submission_state']);
    }

    public static function record(CommunicationTemplate $template, string $event, array $old = [], array $result = [], ?int $userId = null): void
    {
        $template->history()->create(['event' => $event, 'user_id' => $userId ?? auth()->id(),
            'old_values' => $old, 'new_values' => self::values($template),
            'result' => array_intersect_key($result, array_flip(['id', 'status', 'category', 'error_code', 'message_id', 'reason']))]);
    }
}
