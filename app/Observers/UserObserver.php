<?php

namespace App\Observers;

use App\Mail\BienvenueUserMail;
use App\Mail\UserDesactiveMail;
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
                    Mail::to($user->email)->send(new UserDesactiveMail($user));
                }
            } catch (\Throwable $e) {
                logger()->error("Impossible d'envoyer l'email de désactivation à {$user->email}: ".$e->getMessage());
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Skip synthetic or placeholder accounts
        if ($user->email && (
            str_ends_with($user->email, '@ambassadors.local')
            || str_ends_with($user->email, '@facilg.import.local')
            || $user->email === 'migration.facilg@ambassadors.local'
        )) {
            return;
        }

        try {
            $activity = activity('utilisateurs')
                ->event('deleted')
                ->performedOn($user)
                ->withProperties([
                    'email' => $user->email,
                    'name' => $user->name,
                    'statut' => $user->statut,
                ]);

            if (auth()->check()) {
                $activity->causedBy(auth()->user());
            }

            $activity->log("Suppression du compte utilisateur : {$user->name} ({$user->email})");
        } catch (\Throwable $e) {
            logger()->error("Impossible d'enregistrer le journal de suppression pour {$user->email}: ".$e->getMessage());
        }
    }
}
