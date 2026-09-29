<?php

namespace App\Observers;

use App\Mail\BienvenueUserMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        // Enforce must_change_password on initial creation in production/local, skip in tests unless specified
        if (! isset($user->must_change_password)) {
            $user->must_change_password = app()->environment('testing') ? false : true;
        }

        // Store unhashed plain password temporarily on the model instance if generated
        if (empty($user->password)) {
            $plainPassword = Str::random(10);
            $user->password = $plainPassword; // User model has 'password' => 'hashed' cast
            $user->raw_temp_password = $plainPassword;
        }
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        try {
            if ($user->email) {
                Mail::to($user->email)->send(new BienvenueUserMail($user));
            }
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'email de bienvenue à {$user->email}: ".$e->getMessage());
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('statut') && in_array($user->statut, ['inactif', 'suspendu', 'desactive'], true)) {
            try {
                if ($user->email) {
                    Mail::to($user->email)->send(new \App\Mail\UserDesactiveMail($user));
                }
            } catch (\Throwable $e) {
                logger()->error("Impossible d'envoyer l'email de désactivation à {$user->email}: ".$e->getMessage());
            }
        }
    }
}
