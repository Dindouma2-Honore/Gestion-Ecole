<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use DateTimeImmutable;

readonly class DocumentUploadData
{
    public function __construct(
        public string $temporaryPath,
        public string $originalName,
        public string $mimeType,
        public int $size,
        public string $category,
        public string $ownerType,
        public int $ownerId,
        public string $confidentialityLevel = 'interne',
        public ?DateTimeImmutable $expiresAt = null,
    ) {}
}
