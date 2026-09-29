<?php

namespace App\Observers;

use App\Mail\TacheAssigneeMail;
use App\Modules\Socle\Models\Tache;
use Illuminate\Support\Facades\Mail;

class TacheObserver
{
    public function created(Tache $tache): void
    {
        $responsable = $tache->responsable;
        if (! $responsable || ! $responsable->email) {
            return;
        }

        try {
            Mail::to($responsable->email)->send(new TacheAssigneeMail($tache, $responsable));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email d'assignation de tâche à {$responsable->email}: ".$e->getMessage());
        }
    }
}
