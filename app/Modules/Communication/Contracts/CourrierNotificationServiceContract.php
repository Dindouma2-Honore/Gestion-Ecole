<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

interface CourrierNotificationServiceContract
{
    /** Envoie un courrier à une copie figée de contacts fournie par le Socle. */
    public function envoyer(array $destinataires, string $canal, string $objet, string $contenu): array;
}
