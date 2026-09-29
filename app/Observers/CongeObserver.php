<?php

namespace App\Observers;

use App\Mail\CongeNotificationMail;
use App\Modules\RH\Models\Conge;
use Illuminate\Support\Facades\Mail;

class CongeObserver
{
    public function created(Conge $conge): void
    {
        $email = $conge->employe?->email ?? $conge->employe?->user?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new CongeNotificationMail($conge));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email de congé à {$email}: ".$e->getMessage());
        }
    }
}
