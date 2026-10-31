<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

readonly class AcademicContextDTO
{
    public function __construct(
        public AcademicYearData $active,
        public AcademicYearData $selected,
    ) {}
}
