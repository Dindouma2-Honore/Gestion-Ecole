<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Socle\Contracts\NotificationServiceContract;

/**
 * Double de test pour NotificationServiceContract : le module Socle n'a
 * pas encore de dispatch réel par canal, donc les modules qui en
 * dépendent (Finances...) se testent contre ce fake et peuvent inspecter
 * les envois via `$envoyees`.
 */
class FakeNotificationService implements NotificationServiceContract
{
    /** @var array<int, array{canal: string, code: string, eleve_id: int, donnees: array}> */
    public array $envoyees = [];

    public function envoyer(string $canal, string $code, int $eleveId, array $donnees = []): void
    {
        $this->envoyees[] = ['canal' => $canal, 'code' => $code, 'eleve_id' => $eleveId, 'donnees' => $donnees];
    }
}
