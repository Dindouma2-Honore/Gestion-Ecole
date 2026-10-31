<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Enums\PeriodSystem;
use DateTimeImmutable;

readonly class AcademicYearData
{
    public function __construct(
        public int $id,
        public string $label,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public AcademicYearStatus $status,
        public ?PeriodSystem $periodSystem = null,
    ) {}
}
