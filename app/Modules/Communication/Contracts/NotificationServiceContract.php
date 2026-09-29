<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface NotificationServiceContract
{
    /** Envoi asynchrone universel (passant par la file d'attente) */
    public function envoyer(string $canal, string $code, ?object $destinataire, array $donnees = []): object;

    /** Envoi synchrone bloquant, pour les cas où la confirmation immédiate est nécessaire */
    public function envoyerImmediat(string $canal, string $code, ?object $destinataire, array $donnees = []): bool;

    /** Traitement par lot de la file d'attente */
    public function traiterFileAttente(): void;

    /** Marquer une notification comme lue */
    public function marquerLue(int $notificationId): void;

    /** Obtenir l'historique des notifications pour un destinataire */
    public function getHistorique(object $destinataire): Collection;
}
