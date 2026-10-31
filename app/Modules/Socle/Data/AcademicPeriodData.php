<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use App\Modules\Socle\Enums\AcademicPeriodType;
use DateTimeImmutable;

readonly class AcademicPeriodData
{
    public function __construct(
        public int $id,
        public int $academicYearId,
        public string $label,
        public AcademicPeriodType $type,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $order,
        public ?int $parentId = null,
        public bool $closed = false,
    ) {}
}
