<?php

declare(strict_types=1);

namespace App\Modules\Socle\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordAuthenticationActivity
{
    public function handle(Login|Failed|Logout $event): void
    {
        $description = match (true) {
            $event instanceof Login => 'Connexion réussie',
            $event instanceof Logout => 'Déconnexion',
            default => 'Échec de connexion',
        };

        $logger = activity('connexion')
            ->withProperties([
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'email' => $event instanceof Failed ? ($event->credentials['email'] ?? null) : $event->user?->email,
            ]);

        if ($event->user) {
            $logger->causedBy($event->user);
        }

        $logger->log($description);
    }
}
