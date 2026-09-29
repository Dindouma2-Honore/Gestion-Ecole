<?php

namespace App\Modules\RH\Contracts;

interface PointageServiceContract
{
    public function enregistrerPointage(int $employeId, \DateTimeInterface $dateHeure, string $type, string $modePointage, ?string $terminalId = null): void;

    public function corrigerManuel(int $pointageId, ?string $heureArrivee, ?string $heureDepart, string $motif): void;

    public function getJoursAbsenceNonJustifiee(int $employeId, int $mois, int $annee): int;

    public function genererRapportMensuel(int $employeId, int $mois, int $annee): object;
}
