<?php

namespace App\Modules\RH\Contracts;

use Illuminate\Support\Collection;

interface FormationServiceContract
{
    public function creerFormation(array $donnees): object;

    public function inscrireParticipant(int $formationId, int $employeId): void;

    public function marquerPresence(int $formationId, int $employeId, bool $present): void;

    public function getHistoriqueFormations(int $employeId): Collection;

    public function genererAttestation(int $formationId, int $employeId): object;
}
