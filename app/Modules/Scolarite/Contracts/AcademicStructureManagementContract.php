<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

use App\Modules\Scolarite\Data\AcademicStructureItemDTO;

interface AcademicStructureManagementContract
{
    public function createCycle(string $name, string $code, ?string $description = null, bool $active = true, int $order = 0): AcademicStructureItemDTO;

    /** Full replacement of editable fields; returns null when the ID does not exist. */
    public function updateCycle(int $id, string $name, string $code, ?string $description = null, bool $active = true, int $order = 0): ?AcademicStructureItemDTO;

    public function createNiveau(string $name, string $code, ?int $cycleId = null, int $order = 1): AcademicStructureItemDTO;

    /** Full replacement of editable fields; returns null when the ID does not exist. */
    public function updateNiveau(int $id, string $name, string $code, ?int $cycleId = null, int $order = 1): ?AcademicStructureItemDTO;

    public function createFiliere(string $name, string $code, ?string $description = null, bool $active = true): AcademicStructureItemDTO;

    /** Full replacement of editable fields; returns null when the ID does not exist. */
    public function updateFiliere(int $id, string $name, string $code, ?string $description = null, bool $active = true): ?AcademicStructureItemDTO;

    public function createSectionScolaire(string $name, string $code, ?string $description = null, bool $active = true): AcademicStructureItemDTO;

    /** Full replacement of editable fields; returns null when the ID does not exist. */
    public function updateSectionScolaire(int $id, string $name, string $code, ?string $description = null, bool $active = true): ?AcademicStructureItemDTO;

    /** Idempotent import preserving the existing FACILG identifier. */
    public function importNiveau(string $legacyId, string $name, string $code, int $order, ?string $description = null): AcademicStructureItemDTO;
}
