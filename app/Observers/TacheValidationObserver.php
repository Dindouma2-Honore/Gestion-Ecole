<?php

namespace App\Observers;

use App\Mail\TacheValidationMail;
use App\Modules\Socle\Models\TacheValidation;
use Illuminate\Support\Facades\Mail;

class TacheValidationObserver
{
    public function created(TacheValidation $validation): void
    {
        $validateur = $validation->validateur;
        if (! $validateur || ! $validateur->email) {
            return;
        }

        try {
            Mail::to($validateur->email)->send(new TacheValidationMail($validation));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email de demande de validation à {$validateur->email}: ".$e->getMessage());
        }
    }
}
