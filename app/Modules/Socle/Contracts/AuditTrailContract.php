<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Modules\Socle\Data\AuditEntryData;

interface AuditTrailContract
{
    public function record(AuditEntryData $entry): void;

    /** @return list<AuditEntryData> */
    public function historyFor(string $subjectType, int $subjectId): array;
}
