<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Services;

use App\Modules\Socle\Contracts\AcademicCalendarContract;
use App\Modules\VieScolaire\Contracts\InstitutionalCalendarServiceInterface;
use DateTimeInterface;

/**
 * Adaptateur legacy conservé pour les consommateurs Vie scolaire existants.
 */
class InstitutionalCalendarService implements InstitutionalCalendarServiceInterface
{
    public function __construct(private readonly AcademicCalendarContract $calendar) {}

    public function isSchoolOpen(DateTimeInterface $date): bool
    {
        return $this->calendar->isSchoolOpen($date);
    }

    public function isTeachingDay(DateTimeInterface $date, ?int $niveauId = null): bool
    {
        return $this->calendar->isTeachingDay($date, $niveauId);
    }

    public function affectsStaff(DateTimeInterface $date): bool
    {
        return $this->calendar->affectsStaff($date);
    }

    public function affectsStudents(DateTimeInterface $date, ?int $niveauId = null): bool
    {
        return $this->calendar->affectsStudents($date, $niveauId);
    }
}
