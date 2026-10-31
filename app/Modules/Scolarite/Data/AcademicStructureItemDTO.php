<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Data;

readonly class AcademicStructureItemDTO
{
    public function __construct(public int $id, public string $name, public ?int $parentId = null, public ?string $code = null, public bool $active = true, public ?string $description = null, public ?int $order = null) {}
}
