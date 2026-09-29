<?php

namespace App\Observers;

use App\Mail\SanctionNotificationMail;
use App\Modules\RH\Models\SanctionPersonnel;
use Illuminate\Support\Facades\Mail;

class SanctionPersonnelObserver
{
    public function created(SanctionPersonnel $sanction): void
    {
        $email = $sanction->employe?->email ?? $sanction->employe?->user?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new SanctionNotificationMail($sanction));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email de sanction à {$email}: ".$e->getMessage());
        }
    }
}
