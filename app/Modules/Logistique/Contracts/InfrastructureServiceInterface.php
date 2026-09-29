<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;

use Illuminate\Support\Collection;

interface InfrastructureServiceInterface
{
    /**
     * Utilisé par D.26 (Emplois du temps) — indispensable AVANT toute
     * planification de cours pour vérifier qu'une salle est disponible
     * (pas hors service, capacité suffisante).
     */
    public function getSallesDisponibles(?int $niveauId = null, ?int $capaciteMin = null): Collection;

    public function declarerHorsService(int $salleId, string $motif): void;

    public function planifierTravaux(int $salleId, string $description, \DateTimeInterface $dateDebut): object;

    public function terminerTravaux(int $travauxId): void;
}
