<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

use App\Modules\Scolarite\Data\AcademicStructureItemDTO;

interface AcademicStructureReadContract
{
    public function cycle(int $id): ?AcademicStructureItemDTO;

    public function niveau(int $id): ?AcademicStructureItemDTO;

    public function filiere(int $id): ?AcademicStructureItemDTO;

    public function section(int $id): ?AcademicStructureItemDTO;

    /** Lookup used by the existing FACILG import. */
    public function niveauByLegacyId(string $legacyId): ?AcademicStructureItemDTO;
}
