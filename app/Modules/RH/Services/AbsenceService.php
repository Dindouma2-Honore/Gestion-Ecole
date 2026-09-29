<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Models\AbsencePersonnel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AbsenceService implements AbsenceServiceContract
{
    public function getAbsencesNonJustifiees(int $personnelId, Carbon $debut, Carbon $fin): Collection
    {
        return AbsencePersonnel::query()->where('employe_id', $personnelId)->where('type', 'absence_non_justifiee')->where('statut', 'validee')->whereDate('date_debut', '<=', $fin)->whereDate('date_fin', '>=', $debut)->get();
    }

    public function getJoursAbsenceNonJustifiee(int $personnelId, int $mois, int $annee): int
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        return $this->getAbsencesNonJustifiees($personnelId, $debut, $fin)->sum(
            fn ($absence): int => $absence->date_debut->max($debut)->diffInWeekdays($absence->date_fin->min($fin)) + 1,
        );
    }

    public function getJoursSuspension(int $personnelId, int $mois, int $annee): int
    {
        return 0;
    }
}
