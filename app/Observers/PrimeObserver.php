<?php

namespace App\Observers;

use App\Mail\PrimeNotificationMail;
use App\Modules\RH\Models\Prime;
use Illuminate\Support\Facades\Mail;

class PrimeObserver
{
    public function created(Prime $prime): void
    {
        $email = $prime->employe?->email ?? $prime->employe?->user?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new PrimeNotificationMail($prime));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email de prime à {$email}: ".$e->getMessage());
        }
    }
}
