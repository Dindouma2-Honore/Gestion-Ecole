<?php

namespace App\Modules\Communication\Jobs;

use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncWhatsAppTemplatesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public string $wabaId;

    public function __construct(public ?int $templateId = null)
    {
        $this->wabaId = (string) config('whatsapp.waba_id');
    }

    public function uniqueId(): string
    {
        return $this->wabaId.':'.($this->templateId ?? 'all');
    }

    public function backoff(): array
    {
        return [60];
    }

    public function handle(WhatsAppTemplateService $service): void
    {
        if ($this->wabaId !== (string) config('whatsapp.waba_id')) {
            return;
        }
        if ($this->templateId) {
            $template = CommunicationTemplate::find($this->templateId);
            if ($template) {
                $service->syncTemplateStatus($template, true);
            }
        } else {
            $service->syncTemplates(true);
        }
    }
}
