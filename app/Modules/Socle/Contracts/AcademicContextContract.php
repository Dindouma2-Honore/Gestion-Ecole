<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Modules\Socle\Data\AcademicContextDTO;
use App\Modules\Socle\Data\AcademicPeriodData;
use App\Modules\Socle\Data\AcademicYearData;
use App\Modules\Socle\Enums\AcademicYearStatus;

interface AcademicContextContract
{
    /** The sole system-wide active academic year. */
    public function active(): AcademicYearData;

    /** The year selected in the current user's session, or the active year by default. */
    public function selected(): AcademicYearData;

    /** Changes only the current user's session selection. */
    public function select(int $academicYearId): AcademicContextDTO;

    public function status(): AcademicYearStatus;

    public function isPreparation(): bool;

    public function isActive(): bool;

    public function isClosing(): bool;

    public function isClosed(): bool;

    public function isArchived(): bool;

    public function isReadOnly(): bool;

    public function context(): AcademicContextDTO;

    /** @deprecated Use active(). */
    public function currentAcademicYear(): ?AcademicYearData;

    public function academicYear(int $academicYearId): ?AcademicYearData;

    /** @return list<AcademicYearData> */
    public function academicYears(): array;

    /** @return list<AcademicPeriodData> */
    public function periodsFor(int $academicYearId): array;
}
