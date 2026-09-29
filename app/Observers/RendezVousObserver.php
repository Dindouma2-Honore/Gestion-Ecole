<?php

namespace App\Observers;

use App\Mail\RendezVousNotificationMail;
use App\Modules\Communication\Models\RendezVous;
use Illuminate\Support\Facades\Mail;

class RendezVousObserver
{
    public function created(RendezVous $rendezVous): void
    {
        $email = $rendezVous->parent?->email;
        if (! $email && $rendezVous->parent_id) {
            $user = \App\Models\User::find($rendezVous->parent_id);
            $email = $user?->email;
        }

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new RendezVousNotificationMail($rendezVous));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email de rendez-vous à {$email}: ".$e->getMessage());
        }
    }
}
