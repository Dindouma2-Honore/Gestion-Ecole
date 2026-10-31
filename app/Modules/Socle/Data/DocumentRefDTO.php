<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use DateTimeImmutable;

readonly class DocumentRefDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $category,
        public string $mimeType,
        public int $size,
        public string $confidentialityLevel,
        public string $ownerType,
        public int $ownerId,
        public int $version,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt = null,
        public ?string $sha256 = null,
    ) {}
}
