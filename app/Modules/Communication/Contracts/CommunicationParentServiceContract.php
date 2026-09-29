<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

interface CommunicationParentServiceContract
{
    public function envoyerMessageIndividuel(int $parentId, string $sujet, string $contenu): object;

    /** Envoi à toute une classe, un niveau ou tous — résout la liste de parents en interne */
    public function envoyerMessageCollectif(string $cibleType, int $cibleId, string $sujet, string $contenu): object;

    public function repondre(int $messageId, int $parentId, string $contenu): object;

    /** Notification liée à un événement précis */
    public function notifierEvenement(int $parentId, string $typeEvenement, array $donnees): void;
}
