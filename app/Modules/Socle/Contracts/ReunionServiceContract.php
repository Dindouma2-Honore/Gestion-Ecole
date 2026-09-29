<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use DateTimeInterface;

interface ReunionServiceContract
{
    public function planifierReunion(array $donnees, array $participantIds, array $ordreDuJour): object;

    public function marquerPresence(int $reunionId, int $userId, bool $present): void;

    /**
     * Ajoute une décision à une réunion ET génère automatiquement une Tâche liée.
     */
    public function ajouterDecision(int $reunionId, string $description, int $responsableId, DateTimeInterface $echeance): object;

    public function cloturerReunion(int $reunionId, string $compteRendu): void;
}
