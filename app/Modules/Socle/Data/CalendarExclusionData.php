<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use DateTimeImmutable;

readonly class CalendarExclusionData
{
    public function __construct(
        public int $id,
        public string $label,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public bool $closesSchool,
        public bool $affectsTeaching,
        public bool $affectsStaff,
        public ?int $levelId = null,
        public bool $recurring = false,
    ) {}
}
