<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\CourrierNotificationServiceContract;
use App\Modules\Communication\Contracts\NotificationServiceContract;

class CourrierNotificationService implements CourrierNotificationServiceContract
{
    public function __construct(private readonly NotificationServiceContract $notifications) {}

    public function envoyer(array $destinataires, string $canal, string $objet, string $contenu): array
    {
        $resultats = [];
        foreach ($destinataires as $destinataire) {
            $contact = (object) $destinataire;
            $notification = $this->notifications->envoyer(
                $canal,
                'COURRIER_LIBRE',
                $contact,
                ['objet' => $objet, 'contenu' => $contenu],
            );
            $resultats[(int) $contact->snapshot_id] = (int) ($notification->id ?? 0);
        }

        return $resultats;
    }
}
