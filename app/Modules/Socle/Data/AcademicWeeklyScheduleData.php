<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

readonly class AcademicWeeklyScheduleData
{
    public function __construct(
        public int $dayOfWeek,
        public bool $schoolOpen,
        public bool $teachingDay,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
    ) {}
}
