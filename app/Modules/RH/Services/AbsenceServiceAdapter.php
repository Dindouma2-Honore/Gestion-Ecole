<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Contracts\DisciplinePersonnelServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AbsenceServiceAdapter implements AbsenceServiceContract
{
    public function __construct(
        private readonly PointageServiceContract $pointageService,
        private readonly DisciplinePersonnelServiceContract $disciplineService,
    ) {}

    public function getJoursAbsenceNonJustifiee(int $personnelId, int $mois, int $annee): int
    {
        return $this->pointageService->getJoursAbsenceNonJustifiee($personnelId, $mois, $annee);
    }

    public function getJoursSuspension(int $personnelId, int $mois, int $annee): int
    {
        $count = 0;
        $periode = CarbonPeriod::create(
            Carbon::create($annee, $mois, 1),
            Carbon::create($annee, $mois, 1)->endOfMonth()
        );

        foreach ($periode as $jour) {
            if ($jour->isWeekend()) {
                continue;
            }
            if ($this->disciplineService->estSuspenduA($personnelId, $jour)) {
                $count++;
            }
        }

        return $count;
    }

    public function getAbsencesNonJustifiees(int $personnelId, Carbon $debut, Carbon $fin): Collection
    {
        return app(AbsenceService::class)->getAbsencesNonJustifiees($personnelId, $debut, $fin);
    }

    public function getJoursAbsenceNonJustifieePourPeriode(int $personnelId, Carbon $debut, Carbon $fin): int
    {
        return app(AbsenceService::class)->getJoursAbsenceNonJustifieePourPeriode($personnelId, $debut, $fin);
    }

    public function compterAbsencesValideesPourDate(Carbon $date): int
    {
        return app(AbsenceService::class)->compterAbsencesValideesPourDate($date);
    }

    public function enregistrerAbsence(
        int $personnelId,
        \DateTimeInterface $debut,
        \DateTimeInterface $fin,
        string $type,
        string $motif,
        ?int $documentId = null,
        string $statut = 'en_attente',
        ?int $createdBy = null
    ): object {
        return app(AbsenceService::class)->enregistrerAbsence($personnelId, $debut, $fin, $type, $motif, $documentId, $statut, $createdBy);
    }
}
