<?php

declare(strict_types=1);

use App\Modules\Socle\Enums\AcademicPeriodStatus;
use App\Modules\Socle\Enums\AcademicPeriodType;
use App\Modules\Socle\Exceptions\InvalidAcademicPeriodException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;

function periodAcademicYear(): AnneeScolaire
{
    return AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'status' => 'active',
    ]);
}

function academicPeriod(AnneeScolaire $year, array $attributes): Periode
{
    return Periode::query()->create([
        'annee_scolaire_id' => $year->id,
        'libelle' => 'Période',
        'type' => AcademicPeriodType::TRIMESTER,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-15',
        'ordre' => 1,
        ...$attributes,
    ]);
}

it('allows only semesters or trimesters to parent sequences', function (): void {
    $year = periodAcademicYear();
    $semester = academicPeriod($year, [
        'libelle' => 'Semestre 1',
        'type' => AcademicPeriodType::SEMESTER,
        'date_fin' => '2027-01-31',
    ]);
    $sequence = academicPeriod($year, [
        'libelle' => 'Séquence 1',
        'type' => AcademicPeriodType::SEQUENCE,
        'parent_id' => $semester->id,
        'date_fin' => '2026-10-15',
    ]);

    expect($sequence->parent_id)->toBe($semester->id);

    expect(fn () => academicPeriod($year, [
        'libelle' => 'Trimestre enfant',
        'type' => AcademicPeriodType::TRIMESTER,
        'parent_id' => $semester->id,
        'date_fin' => '2026-10-31',
    ]))->toThrow(InvalidAcademicPeriodException::class);

    expect(fn () => academicPeriod($year, [
        'libelle' => 'Séquence sans parent',
        'type' => AcademicPeriodType::SEQUENCE,
        'ordre' => 2,
        'date_debut' => '2026-11-01',
        'date_fin' => '2026-11-15',
    ]))->toThrow(InvalidAcademicPeriodException::class);
});

it('rejects dates outside the year, outside the parent, incoherent order and overlaps', function (): void {
    $year = periodAcademicYear();
    $trimester = academicPeriod($year, [
        'libelle' => 'Trimestre 1',
        'date_fin' => '2026-12-15',
    ]);
    academicPeriod($year, [
        'libelle' => 'Séquence 1',
        'type' => AcademicPeriodType::SEQUENCE,
        'parent_id' => $trimester->id,
        'date_fin' => '2026-10-15',
    ]);

    expect(fn () => academicPeriod($year, [
        'libelle' => 'Hors année',
        'type' => AcademicPeriodType::SEMESTER,
        'ordre' => 2,
        'date_debut' => '2026-08-31',
        'date_fin' => '2027-01-31',
    ]))->toThrow(InvalidAcademicPeriodException::class);

    expect(fn () => academicPeriod($year, [
        'libelle' => 'Hors parent',
        'type' => AcademicPeriodType::SEQUENCE,
        'parent_id' => $trimester->id,
        'ordre' => 2,
        'date_debut' => '2026-12-16',
        'date_fin' => '2026-12-31',
    ]))->toThrow(InvalidAcademicPeriodException::class);

    expect(fn () => academicPeriod($year, [
        'libelle' => 'Chevauchement',
        'type' => AcademicPeriodType::SEQUENCE,
        'parent_id' => $trimester->id,
        'ordre' => 2,
        'date_debut' => '2026-10-10',
        'date_fin' => '2026-11-01',
    ]))->toThrow(InvalidAcademicPeriodException::class);
});

it('keeps cloturee compatibility while exposing an explicit period status', function (): void {
    $period = academicPeriod(periodAcademicYear(), ['cloturee' => true]);

    expect($period->status)->toBe(AcademicPeriodStatus::CLOSED)
        ->and($period->cloturee)->toBeTrue()
        ->and($period->is_closed)->toBeTrue();
});
