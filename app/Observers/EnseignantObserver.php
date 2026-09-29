<?php

namespace App\Observers;

use App\Mail\EnseignantCreeMail;
use App\Modules\RH\Models\Enseignant;
use Illuminate\Support\Facades\Mail;

class EnseignantObserver
{
    public function created(Enseignant $enseignant): void
    {
        $email = $enseignant->employe?->email ?? $enseignant->employe?->user?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new EnseignantCreeMail($enseignant));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email de création enseignant à {$email}: ".$e->getMessage());
        }
    }
}
