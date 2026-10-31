<?php

declare(strict_types=1);

use App\Modules\Socle\Contracts\AuditTrailContract;
use App\Modules\Socle\Data\AuditEntryData;
use App\Modules\Socle\Models\ActivityLog;

it('writes an audit entry through AuditTrailService', function (): void {
    app(AuditTrailContract::class)->record(new AuditEntryData('Tests\\Subject', 17, 'Modification enregistrée', properties: ['metadata' => ['source' => 'test']]));

    expect(ActivityLog::query()->where('subject_type', 'Tests\\Subject')->where('subject_id', 17)->value('description'))->toBe('Modification enregistrée');
});

it('preserves old values new values and metadata', function (): void {
    app(AuditTrailContract::class)->record(new AuditEntryData('Tests\\Subject', 18, 'Mise à jour', properties: ['old_values' => ['statut' => 'brouillon'], 'new_values' => ['statut' => 'valide'], 'metadata' => ['source' => 'api']]));
    $properties = ActivityLog::query()->where('subject_id', 18)->firstOrFail()->properties->all();

    expect($properties['old_values']['statut'])->toBe('brouillon')->and($properties['new_values']['statut'])->toBe('valide')->and($properties['metadata']['source'])->toBe('api');
});

it('redacts secrets recursively before audit storage', function (): void {
    app(AuditTrailContract::class)->record(new AuditEntryData('Tests\\Subject', 19, 'Secret', properties: ['password' => 'x', 'metadata' => ['token' => 'y', 'safe' => 'z']]));
    $properties = ActivityLog::query()->where('subject_id', 19)->firstOrFail()->properties->all();

    expect($properties['password'])->toBe('[REDACTED]')->and($properties['metadata']['token'])->toBe('[REDACTED]')->and($properties['metadata']['safe'])->toBe('z');
});

it('keeps legacy activity log entries readable', function (): void {
    ActivityLog::query()->create(['log_name' => 'legacy', 'description' => 'Historique legacy', 'subject_type' => 'Tests\\Legacy', 'subject_id' => 20, 'event' => 'updated', 'properties' => []]);

    expect(app(AuditTrailContract::class)->historyFor('Tests\\Legacy', 20))->toHaveCount(1)->and(app(AuditTrailContract::class)->historyFor('Tests\\Legacy', 20)[0]->description)->toBe('Historique legacy');
});

it('uses immutable audit data and preserves its timestamp', function (): void {
    $at = new DateTimeImmutable('2026-09-30 12:00:00');
    $entry = new AuditEntryData('Tests\\Subject', 21, 'Daté', occurredAt: $at);
    app(AuditTrailContract::class)->record($entry);

    expect((new ReflectionClass($entry))->isReadOnly())->toBeTrue()->and(ActivityLog::query()->where('subject_id', 21)->firstOrFail()->created_at->toDateTimeString())->toBe('2026-09-30 12:00:00');
});
