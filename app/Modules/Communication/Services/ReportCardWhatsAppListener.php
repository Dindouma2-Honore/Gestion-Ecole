<?php

namespace App\Modules\Communication\Services;

use App\Modules\Pedagogie\Contracts\ReportCardPublished;

class ReportCardWhatsAppListener
{
    public function handle(ReportCardPublished $event): void
    {
        // Real report cards must never leave private storage during test phase.
        app(WhatsAppDispatcher::class)->dispatch('', ['event' => 'report_card_published', 'bulletin_id' => $event->bulletinId], 'report-card:'.$event->bulletinId, true);
    }
}
