<?php

declare(strict_types=1);

use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Enums\PeriodSystem;
use App\Modules\Socle\Exceptions\AnneeVerrouilleeException;
use App\Modules\Socle\Exceptions\TransitionStatutInvalideException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;
use Illuminate\Database\QueryException;

function academicYear(string $label, string $legacyStatus = 'brouillon'): AnneeScolaire
{
    $startYear = (int) substr($label, 0, 4);

    return AnneeScolaire::query()->create([
        'libelle' => $label,
        'date_debut' => sprintf('%d-09-01', $startYear),
        'date_fin' => sprintf('%d-07-31', $startYear + 1),
        'statut' => $legacyStatus,
    ]);
}

it('keeps exactly one active year through the lifecycle and database constraint', function (): void {
    $active = academicYear('2025-2026', 'active');
    $next = academicYear('2026-2027');

    app(AnneeScolaireServiceContract::class)->activerAnnee($next->id);

    expect($active->fresh()->status)->toBe(AcademicYearStatus::CLOTUREE)
        ->and($active->fresh()->statut)->toBe(AnneeScolaire::STATUT_CLOTUREE)
        ->and($next->fresh()->status)->toBe(AcademicYearStatus::ACTIVE)
        ->and(AnneeScolaire::query()->where('status', AcademicYearStatus::ACTIVE->value)->count())->toBe(1);

    expect(fn () => AnneeScolaire::query()->create([
        'libelle' => '2027-2028',
        'date_debut' => '2027-09-01',
        'date_fin' => '2028-07-31',
        'status' => AcademicYearStatus::ACTIVE,
    ]))->toThrow(QueryException::class);
});

it('allows only controlled lifecycle transitions', function (): void {
    $year = academicYear('2026-2027');
    $service = app(AnneeScolaireServiceContract::class);

    expect(fn () => $service->archiverAnnee($year->id))->toThrow(TransitionStatutInvalideException::class);

    $service->activerAnnee($year->id);
    $service->demarrerClotureAnnee($year->id);
    expect($year->fresh()->status)->toBe(AcademicYearStatus::CLOTURE_EN_COURS);

    $service->finaliserClotureAnnee($year->id);
    $service->archiverAnnee($year->id);
    expect($year->fresh()->status)->toBe(AcademicYearStatus::ARCHIVEE);
});

it('makes closed and archived years readonly on the server', function (): void {
    $closed = academicYear('2025-2026', 'cloturee');

    expect(fn () => $closed->update(['libelle' => 'Modification interdite']))->toThrow(AnneeVerrouilleeException::class);

    $archived = academicYear('2024-2025', 'archivee');
    expect(fn () => $archived->delete())->toThrow(AnneeVerrouilleeException::class);
});

it('maps legacy status values and exposes deprecated is_current compatibility', function (): void {
    $year = academicYear('2026-2027', 'active');

    expect($year->status)->toBe(AcademicYearStatus::ACTIVE)
        ->and($year->period_system)->toBe(PeriodSystem::TRIMESTER)
        ->and($year->is_current)->toBeTrue();
});

it('blocks create, update and delete of a period once its year is closed', function (): void {
    $year = academicYear('2026-2027', 'active');
    $periode = Periode::query()->create([
        'annee_scolaire_id' => $year->id,
        'libelle' => 'Premier trimestre',
        'type' => 'trimestre',
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-15',
        'ordre' => 1,
    ]);

    $service = app(AnneeScolaireServiceContract::class);
    $service->demarrerClotureAnnee($year->id);
    $service->finaliserClotureAnnee($year->id);

    expect($year->fresh()->status)->toBe(AcademicYearStatus::CLOTUREE);

    expect(fn () => $periode->update(['libelle' => 'Renommage interdit']))->toThrow(AnneeVerrouilleeException::class);
    expect(fn () => $periode->delete())->toThrow(AnneeVerrouilleeException::class);
    expect(fn () => Periode::query()->create([
        'annee_scolaire_id' => $year->id,
        'libelle' => 'Deuxième trimestre',
        'type' => 'trimestre',
        'date_debut' => '2026-12-16',
        'date_fin' => '2027-03-31',
        'ordre' => 2,
    ]))->toThrow(AnneeVerrouilleeException::class);

    expect(Periode::query()->count())->toBe(1)
        ->and($periode->fresh()->libelle)->toBe('Premier trimestre');
});

it('blocks create, update and delete of a period once its year is archived', function (): void {
    $year = academicYear('2026-2027', 'active');
    $periode = Periode::query()->create([
        'annee_scolaire_id' => $year->id,
        'libelle' => 'Premier trimestre',
        'type' => 'trimestre',
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-15',
        'ordre' => 1,
    ]);

    $service = app(AnneeScolaireServiceContract::class);
    $service->demarrerClotureAnnee($year->id);
    $service->finaliserClotureAnnee($year->id);
    $service->archiverAnnee($year->id);

    expect($year->fresh()->status)->toBe(AcademicYearStatus::ARCHIVEE);

    expect(fn () => $periode->update(['libelle' => 'Renommage interdit']))->toThrow(AnneeVerrouilleeException::class);
    expect(fn () => $periode->delete())->toThrow(AnneeVerrouilleeException::class);
    expect(fn () => Periode::query()->create([
        'annee_scolaire_id' => $year->id,
        'libelle' => 'Deuxième trimestre',
        'type' => 'trimestre',
        'date_debut' => '2026-12-16',
        'date_fin' => '2027-03-31',
        'ordre' => 2,
    ]))->toThrow(AnneeVerrouilleeException::class);

    expect(Periode::query()->count())->toBe(1)
        ->and($periode->fresh()->libelle)->toBe('Premier trimestre');
});
