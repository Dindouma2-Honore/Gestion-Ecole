<?php

namespace App\Observers;

use App\Mail\EmployeCreeMail;
use App\Modules\RH\Models\Employe;
use Illuminate\Support\Facades\Mail;

class EmployeObserver
{
    public function created(Employe $employe): void
    {
        $email = $employe->email ?? $employe->user?->email;
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new EmployeCreeMail($employe));
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email de création employé à {$email}: ".$e->getMessage());
        }
    }
}
