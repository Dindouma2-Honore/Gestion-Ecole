<?php

declare(strict_types=1);

namespace App\Modules\RH\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface AbsenceServiceContract
{
    public function getAbsencesNonJustifiees(int $personnelId, Carbon $debut, Carbon $fin): Collection;

    public function getJoursAbsenceNonJustifiee(int $personnelId, int $mois, int $annee): int;

    /** Jours ouvrés (lun-ven) d'absence non justifiée validée recoupant la période, bornes incluses. */
    public function getJoursAbsenceNonJustifieePourPeriode(int $personnelId, Carbon $debut, Carbon $fin): int;

    public function getJoursSuspension(int $personnelId, int $mois, int $annee): int;

    public function compterAbsencesValideesPourDate(Carbon $date): int;

    public function enregistrerAbsence(
        int $personnelId,
        \DateTimeInterface $debut,
        \DateTimeInterface $fin,
        string $type,
        string $motif,
        ?int $documentId = null,
        string $statut = 'en_attente',
        ?int $createdBy = null
    ): object;
}
