<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use DateTimeImmutable;

readonly class AuditEntryData
{
    /** @param array<string, mixed> $properties */
    public function __construct(
        public string $subjectType,
        public int $subjectId,
        public string $description,
        public ?int $actorUserId = null,
        public ?string $reason = null,
        public array $properties = [],
        public ?DateTimeImmutable $occurredAt = null,
    ) {}
}
