<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Contracts\DisciplinePersonnelServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

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
}
