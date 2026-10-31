<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Modules\Socle\Data\AcademicWeeklyScheduleData;
use App\Modules\Socle\Data\CalendarExclusionData;
use DateTimeInterface;

interface AcademicCalendarContract
{
    /** @return list<AcademicWeeklyScheduleData> */
    public function weeklySchedule(): array;

    /** @return list<CalendarExclusionData> */
    public function exceptionsOn(DateTimeInterface $date, ?int $levelId = null): array;

    public function isSchoolOpen(DateTimeInterface $date): bool;

    public function isTeachingDay(DateTimeInterface $date, ?int $levelId = null): bool;

    public function affectsStaff(DateTimeInterface $date): bool;

    public function affectsStudents(DateTimeInterface $date, ?int $levelId = null): bool;
}
