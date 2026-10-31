<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Socle\Contracts\AcademicContextContract;
use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Models\AnneeScolaire;

function contextYear(string $label, string $status): AnneeScolaire
{
    $startYear = (int) substr($label, 0, 4);

    return AnneeScolaire::query()->create([
        'libelle' => $label,
        'date_debut' => sprintf('%d-09-01', $startYear),
        'date_fin' => sprintf('%d-07-31', $startYear + 1),
        'status' => $status,
    ]);
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['statut' => 'actif']));
});

it('separates the system active year from the user selected year', function (): void {
    $active = contextYear('2026-2027', 'active');
    $archived = contextYear('2025-2026', 'archivee');
    $context = app(AcademicContextContract::class);

    expect($context->active()->id)->toBe($active->id)
        ->and($context->selected()->id)->toBe($active->id);

    $result = $context->select($archived->id);

    expect($result->active->id)->toBe($active->id)
        ->and($result->selected->id)->toBe($archived->id)
        ->and($context->status())->toBe(AcademicYearStatus::ARCHIVEE)
        ->and($context->isArchived())->toBeTrue()
        ->and($context->isReadOnly())->toBeTrue()
        ->and($active->fresh()->status)->toBe(AcademicYearStatus::ACTIVE)
        ->and($archived->fresh()->status)->toBe(AcademicYearStatus::ARCHIVEE);
});

it('reports the selected year lifecycle without changing it', function (): void {
    $active = contextYear('2026-2027', 'active');
    $closing = contextYear('2025-2026', 'cloture_en_cours');
    $context = app(AcademicContextContract::class);

    $context->select($closing->id);

    expect($context->isPreparation())->toBeFalse()
        ->and($context->isActive())->toBeFalse()
        ->and($context->isClosing())->toBeTrue()
        ->and($context->isClosed())->toBeFalse()
        ->and($context->isReadOnly())->toBeFalse()
        ->and($active->fresh()->status)->toBe(AcademicYearStatus::ACTIVE)
        ->and($closing->fresh()->status)->toBe(AcademicYearStatus::CLOTURE_EN_COURS);
});
