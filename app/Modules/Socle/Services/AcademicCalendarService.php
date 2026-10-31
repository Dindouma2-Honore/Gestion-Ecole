<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\AcademicCalendarContract;
use App\Modules\Socle\Contracts\SchoolProfileContract;
use App\Modules\Socle\Data\AcademicWeeklyScheduleData;
use App\Modules\Socle\Data\CalendarExclusionData;
use App\Modules\Socle\Models\AcademicWeeklySchedule;
use App\Modules\Socle\Models\JourFerie;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class AcademicCalendarService implements AcademicCalendarContract
{
    public function __construct(private readonly SchoolProfileContract $schoolProfile) {}

    public function weeklySchedule(): array
    {
        $schedule = AcademicWeeklySchedule::query()->orderBy('day_of_week')->get();
        if ($schedule->isEmpty()) {
            $workingDays = $this->schoolProfile->profile()->workingDays;
            $workingDays = $workingDays === [] ? [1, 2, 3, 4, 5, 6] : $workingDays;

            return array_map(
                fn (int $day): AcademicWeeklyScheduleData => new AcademicWeeklyScheduleData($day, in_array($day, $workingDays, true), in_array($day, $workingDays, true)),
                range(1, 7),
            );
        }

        return $schedule->map(fn (AcademicWeeklySchedule $day): AcademicWeeklyScheduleData => new AcademicWeeklyScheduleData(
            dayOfWeek: $day->day_of_week,
            schoolOpen: $day->is_school_open,
            teachingDay: $day->is_teaching_day,
            startsAt: $day->starts_at,
            endsAt: $day->ends_at,
        ))->all();
    }

    public function exceptionsOn(DateTimeInterface $date, ?int $levelId = null): array
    {
        $day = Carbon::instance($date)->startOfDay();

        return JourFerie::query()
            ->where(fn ($query) => $query->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee', 'inactif']))
            ->when($levelId !== null, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('niveau_id')->orWhere('niveau_id', $levelId)))
            ->get()
            ->filter(fn (JourFerie $exception): bool => $this->appliesOn($exception, $day))
            ->map(fn (JourFerie $exception): CalendarExclusionData => new CalendarExclusionData(
                id: (int) $exception->id,
                label: (string) $exception->libelle,
                startsAt: DateTimeImmutable::createFromInterface($exception->date_debut ?? $exception->date),
                endsAt: DateTimeImmutable::createFromInterface($exception->date_fin ?? $exception->date_debut ?? $exception->date),
                closesSchool: (bool) $exception->etablissement_ferme,
                affectsTeaching: (bool) $exception->affecte_cours,
                affectsStaff: (bool) $exception->affecte_personnel,
                levelId: $exception->niveau_id === null ? null : (int) $exception->niveau_id,
                recurring: (bool) $exception->recurrent,
            ))
            ->values()
            ->all();
    }

    public function isSchoolOpen(DateTimeInterface $date): bool
    {
        $day = Carbon::instance($date)->dayOfWeekIso;
        $schedule = collect($this->weeklySchedule())->firstWhere('dayOfWeek', $day);

        return ($schedule?->schoolOpen ?? false)
            && ! collect($this->exceptionsOn($date))->contains(fn (CalendarExclusionData $exception): bool => $exception->closesSchool);
    }

    public function isTeachingDay(DateTimeInterface $date, ?int $levelId = null): bool
    {
        $day = Carbon::instance($date)->dayOfWeekIso;
        $schedule = collect($this->weeklySchedule())->firstWhere('dayOfWeek', $day);

        return $this->isSchoolOpen($date)
            && ($schedule?->teachingDay ?? false)
            && ! collect($this->exceptionsOn($date, $levelId))->contains(fn (CalendarExclusionData $exception): bool => $exception->affectsTeaching);
    }

    public function affectsStaff(DateTimeInterface $date): bool
    {
        return collect($this->exceptionsOn($date))->contains(fn (CalendarExclusionData $exception): bool => $exception->affectsStaff);
    }

    public function affectsStudents(DateTimeInterface $date, ?int $levelId = null): bool
    {
        return collect($this->exceptionsOn($date, $levelId))->contains(fn (CalendarExclusionData $exception): bool => $exception->affectsTeaching);
    }

    private function appliesOn(JourFerie $exception, Carbon $day): bool
    {
        $startsAt = Carbon::instance($exception->date_debut ?? $exception->date)->startOfDay();
        $endsAt = Carbon::instance($exception->date_fin ?? $exception->date_debut ?? $exception->date)->endOfDay();

        if (! $exception->recurrent) {
            return $day->betweenIncluded($startsAt, $endsAt);
        }

        $jour = $day->format('m-d');
        $debut = $startsAt->format('m-d');
        $fin = $endsAt->format('m-d');

        if ($debut <= $fin) {
            return $jour >= $debut && $jour <= $fin;
        }

        return $jour >= $debut || $jour <= $fin;
    }
}
