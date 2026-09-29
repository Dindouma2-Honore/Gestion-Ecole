<?php

declare(strict_types=1);

namespace App\Modules\RH\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface AbsenceServiceContract
{
    public function getAbsencesNonJustifiees(int $personnelId, Carbon $debut, Carbon $fin): Collection;

    public function getJoursAbsenceNonJustifiee(int $personnelId, int $mois, int $annee): int;

    public function getJoursSuspension(int $personnelId, int $mois, int $annee): int;
}
