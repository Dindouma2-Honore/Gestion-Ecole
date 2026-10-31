<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Contracts;

use DateTimeInterface;

interface InstitutionalCalendarServiceInterface
{
    public function isSchoolOpen(DateTimeInterface $date): bool;

    public function isTeachingDay(DateTimeInterface $date, ?int $niveauId = null): bool;

    public function affectsStaff(DateTimeInterface $date): bool;

    public function affectsStudents(DateTimeInterface $date, ?int $niveauId = null): bool;
}
