<?php

declare(strict_types=1);

namespace App\Modules\Socle\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateDerniereConnexion
{
    public function handle(Login $event): void
    {
        $event->user->forceFill([
            'derniere_connexion_at' => now(),
        ])->saveQuietly();
    }
}
