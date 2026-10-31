<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\AuditTrailContract;
use App\Modules\Socle\Data\AuditEntryData;
use App\Modules\Socle\Models\ActivityLog;

final class AuditTrailService implements AuditTrailContract
{
    public function record(AuditEntryData $e): void
    {
        ActivityLog::query()->create(['log_name' => 'audit', 'description' => $e->description, 'subject_type' => $e->subjectType, 'subject_id' => $e->subjectId, 'causer_type' => $e->actorUserId ? 'App\\Models\\User' : null, 'causer_id' => $e->actorUserId, 'properties' => $this->redact($e->properties), 'event' => 'audit', 'created_at' => $e->occurredAt, 'updated_at' => $e->occurredAt]);
    }

    public function historyFor(string $t, int $id): array
    {
        return ActivityLog::query()->where('subject_type', $t)->where('subject_id', $id)->latest()->get()->map(fn ($a) => new AuditEntryData($t, $id, $a->description, $a->causer_id, $a->motif, $a->properties->all(), $a->created_at?->toImmutable()))->all();
    }

    private function redact(array $v): array
    {
        foreach ($v as $k => $x) {
            $v[$k] = preg_match('/password|token|secret|authorization/i', (string) $k) ? '[REDACTED]' : (is_array($x) ? $this->redact($x) : $x);
        }

return $v;
    }
}
