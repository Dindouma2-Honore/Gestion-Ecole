<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

use App\Modules\Scolarite\Data\AcademicStructureItemDTO;

interface AcademicStructureCatalogContract
{
    /** @return array<int, AcademicStructureItemDTO> Records keyed by ID. Null filter selects all. */
    public function cycles(?bool $active = null): array;

    /** @return array<int, AcademicStructureItemDTO> Records keyed by ID. Null filter selects all. */
    public function niveaux(?int $cycleId = null): array;

    /** @return array<int, AcademicStructureItemDTO> Records keyed by ID. Null filter selects all. */
    public function filieres(?bool $active = null): array;

    /** @return array<int, AcademicStructureItemDTO> Records keyed by ID. Null filter selects all. */
    public function sections(?bool $active = null): array;
}
