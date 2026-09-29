<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;
use Illuminate\Http\UploadedFile;

interface EvenementServiceInterface
{
    public function creerEvenement(array $donnees): object;

    public function inscrireParticipant(int $evenementId, string $participantType, int $participantId): void;

    /** @throws AutorisationParentaleManquanteException */
    public function confirmerParticipation(int $evenementParticipantId): void;

    public function enregistrerAutorisationParentale(int $evenementParticipantId, UploadedFile $document): void;

    public function cloturerEvenement(int $evenementId, string $compteRendu): void;
}
