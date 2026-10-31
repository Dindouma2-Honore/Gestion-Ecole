<?php

declare(strict_types=1);

namespace App\Modules\RH\Listeners;

use App\Modules\Communication\Services\WhatsAppDispatcher;
use App\Modules\RH\Events\CandidateApplicationAccepted;
use App\Modules\RH\Events\CandidateApplicationRejected;

class CandidateApplicationWhatsAppListener
{
    public function handle(CandidateApplicationAccepted|CandidateApplicationRejected $event): void
    {
        $template = match (true) {
            $event instanceof CandidateApplicationAccepted => 'candidate_application_accepted',
            $event instanceof CandidateApplicationRejected => 'candidate_application_rejected',
        };

        $recipient = preg_replace('/\D+/', '', (string) ($event->phone ?? ''));
        if ($recipient === '') {
            return;
        }

        app(WhatsAppDispatcher::class)->dispatch(
            $recipient,
            ['type' => 'template', 'template' => ['name' => $template, 'language' => ['code' => 'fr']]],
            'candidate-application:'.$event->candidatureId.':'.strtolower(class_basename($event)),
            true,
        );
    }
}
