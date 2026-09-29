<?php

namespace App\Observers;

use App\Mail\InspectionPedagogiqueMail;
use App\Modules\RH\Models\Inspection;
use Illuminate\Support\Facades\Mail;

class InspectionObserver
{
    public function created(Inspection $inspection): void
    {
        $email = $inspection->enseignant?->employe?->email ?? $inspection->enseignant?->employe?->user?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new InspectionPedagogiqueMail($inspection));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email d'inspection à {$email}: ".$e->getMessage());
        }
    }
}
