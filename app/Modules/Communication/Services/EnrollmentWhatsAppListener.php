<?php

namespace App\Modules\Communication\Services;

use App\Modules\Scolarite\Contracts\EnrollmentValidated;

class EnrollmentWhatsAppListener
{
    public function handle(EnrollmentValidated $event): void
    {
        // Business events are simulations only during the Meta test phase.
        app(WhatsAppDispatcher::class)->dispatch($event->parentTelephone ?? '', ['type' => 'template', 'template' => ['name' => 'inscription_validee', 'language' => ['code' => 'fr']]], 'enrollment:'.$event->inscriptionId, true);
    }
}
