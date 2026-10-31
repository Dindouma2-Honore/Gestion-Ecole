<?php

declare(strict_types=1);

use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Contracts\AcademicCalendarContract;
use App\Modules\Socle\Models\AcademicWeeklySchedule;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\JourFerie;

beforeEach(function (): void {
    foreach (range(1, 7) as $day) {
        AcademicWeeklySchedule::query()->updateOrCreate(
            ['day_of_week' => $day],
            ['is_school_open' => $day <= 5, 'is_teaching_day' => $day <= 5],
        );
    }
});

it('uses the weekly schedule as the standard institutional calendar', function (): void {
    $calendar = app(AcademicCalendarContract::class);

    expect($calendar->isSchoolOpen(new DateTimeImmutable('2026-09-14')))->toBeTrue()
        ->and($calendar->isTeachingDay(new DateTimeImmutable('2026-09-14')))->toBeTrue()
        ->and($calendar->isSchoolOpen(new DateTimeImmutable('2026-09-19')))->toBeFalse();
});

it('reuses existing JourFerie records as calendar exceptions', function (): void {
    JourFerie::query()->create([
        'libelle' => 'Fermeture exceptionnelle',
        'date' => '2026-09-14',
        'date_debut' => '2026-09-14',
        'date_fin' => '2026-09-14',
        'etablissement_ferme' => true,
        'affecte_cours' => true,
        'affecte_personnel' => true,
        'statut' => 'actif',
    ]);
    $calendar = app(AcademicCalendarContract::class);

    expect($calendar->isSchoolOpen(new DateTimeImmutable('2026-09-14')))->toBeFalse()
        ->and($calendar->isTeachingDay(new DateTimeImmutable('2026-09-14')))->toBeFalse()
        ->and($calendar->affectsStaff(new DateTimeImmutable('2026-09-14')))->toBeTrue()
        ->and($calendar->affectsStudents(new DateTimeImmutable('2026-09-14')))->toBeTrue()
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2026-09-14')))->toHaveCount(1);
});

it('applies a teaching-only exception without closing the school', function (): void {
    JourFerie::query()->create([
        'libelle' => 'Journée pédagogique',
        'date' => '2026-09-14',
        'date_debut' => '2026-09-14',
        'date_fin' => '2026-09-14',
        'etablissement_ferme' => false,
        'affecte_cours' => true,
        'affecte_personnel' => false,
        'statut' => 'actif',
    ]);
    $calendar = app(AcademicCalendarContract::class);

    expect($calendar->isSchoolOpen(new DateTimeImmutable('2026-09-14')))->toBeTrue()
        ->and($calendar->isTeachingDay(new DateTimeImmutable('2026-09-14')))->toBeFalse()
        ->and($calendar->affectsStudents(new DateTimeImmutable('2026-09-14')))->toBeTrue()
        ->and($calendar->affectsStaff(new DateTimeImmutable('2026-09-14')))->toBeFalse();
});

it('applies a recurring exception on the same month-day range in later years', function (): void {
    JourFerie::query()->create([
        'libelle' => 'Fermeture de fin d’année',
        'date' => '2026-12-24',
        'date_debut' => '2026-12-24',
        'date_fin' => '2026-12-26',
        'recurrent' => true,
        'etablissement_ferme' => true,
        'affecte_cours' => true,
        'affecte_personnel' => true,
        'statut' => 'actif',
    ]);
    $calendar = app(AcademicCalendarContract::class);

    expect($calendar->isSchoolOpen(new DateTimeImmutable('2026-12-25')))->toBeFalse()
        ->and($calendar->isSchoolOpen(new DateTimeImmutable('2027-12-24')))->toBeFalse()
        ->and($calendar->isSchoolOpen(new DateTimeImmutable('2027-12-23')))->toBeTrue()
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2027-12-24')))->toHaveCount(1);
});

it('keeps the standard week in force outside the academic year and bounds exceptions to their own range', function (): void {
    AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'statut' => 'brouillon',
    ]);
    JourFerie::query()->create([
        'libelle' => 'Fermeture exceptionnelle',
        'date' => '2026-09-14',
        'date_debut' => '2026-09-14',
        'date_fin' => '2026-09-14',
        'etablissement_ferme' => true,
        'affecte_cours' => true,
        'affecte_personnel' => true,
        'statut' => 'actif',
    ]);
    $calendar = app(AcademicCalendarContract::class);

    expect($calendar->isSchoolOpen(new DateTimeImmutable('2028-08-07')))->toBeTrue()
        ->and($calendar->isSchoolOpen(new DateTimeImmutable('2028-08-12')))->toBeFalse()
        ->and($calendar->isSchoolOpen(new DateTimeImmutable('2026-09-15')))->toBeTrue()
        ->and($calendar->isSchoolOpen(new DateTimeImmutable('2026-09-14')))->toBeFalse()
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2028-08-07')))->toBeEmpty();
});

it('scopes exceptions and teaching days to the requested level', function (): void {
    $primaire = Niveau::query()->create(['nom' => 'Primaire', 'code' => 'PRI', 'ordre' => 1]);
    $secondaire = Niveau::query()->create(['nom' => 'Secondaire', 'code' => 'SEC', 'ordre' => 2]);

    JourFerie::query()->create([
        'libelle' => 'Journée pédagogique tous niveaux',
        'date' => '2026-09-14',
        'date_debut' => '2026-09-14',
        'date_fin' => '2026-09-14',
        'etablissement_ferme' => false,
        'affecte_cours' => true,
        'affecte_personnel' => false,
        'statut' => 'actif',
    ]);
    JourFerie::query()->create([
        'libelle' => 'Sortie du niveau Primaire',
        'date' => '2026-09-15',
        'date_debut' => '2026-09-15',
        'date_fin' => '2026-09-15',
        'niveau_id' => $primaire->id,
        'etablissement_ferme' => false,
        'affecte_cours' => true,
        'affecte_personnel' => false,
        'statut' => 'actif',
    ]);
    $calendar = app(AcademicCalendarContract::class);

    expect($calendar->exceptionsOn(new DateTimeImmutable('2026-09-14'), $primaire->id))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2026-09-14'), $secondaire->id))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2026-09-14')))->toHaveCount(1)

        ->and($calendar->exceptionsOn(new DateTimeImmutable('2026-09-15'), $primaire->id))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2026-09-15'), $secondaire->id))->toBeEmpty()
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2026-09-15')))->toHaveCount(1)

        ->and($calendar->isTeachingDay(new DateTimeImmutable('2026-09-14'), $primaire->id))->toBeFalse()
        ->and($calendar->isTeachingDay(new DateTimeImmutable('2026-09-14'), $secondaire->id))->toBeFalse()

        ->and($calendar->isTeachingDay(new DateTimeImmutable('2026-09-15'), $primaire->id))->toBeFalse()
        ->and($calendar->isTeachingDay(new DateTimeImmutable('2026-09-15'), $secondaire->id))->toBeTrue()

        ->and($calendar->affectsStudents(new DateTimeImmutable('2026-09-15'), $primaire->id))->toBeTrue()
        ->and($calendar->affectsStudents(new DateTimeImmutable('2026-09-15'), $secondaire->id))->toBeFalse();
});

it('applies a recurring exception whose range crosses the year boundary', function (): void {
    JourFerie::query()->create([
        'libelle' => 'Fermeture de fin et début d’année',
        'date' => '2026-12-31',
        'date_debut' => '2026-12-31',
        'date_fin' => '2027-01-02',
        'recurrent' => true,
        'etablissement_ferme' => true,
        'affecte_cours' => true,
        'affecte_personnel' => true,
        'statut' => 'actif',
    ]);
    $calendar = app(AcademicCalendarContract::class);

    expect($calendar->exceptionsOn(new DateTimeImmutable('2026-12-31')))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2027-01-01')))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2027-01-02')))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2027-01-03')))->toBeEmpty()

        ->and($calendar->isSchoolOpen(new DateTimeImmutable('2026-12-31')))->toBeFalse()
        ->and($calendar->isSchoolOpen(new DateTimeImmutable('2027-01-01')))->toBeFalse()
        ->and($calendar->affectsStudents(new DateTimeImmutable('2027-01-03')))->toBeFalse();
});

it('still applies a normal recurring range that does not cross the year boundary', function (): void {
    JourFerie::query()->create([
        'libelle' => 'Fermeture de printemps',
        'date' => '2027-01-05',
        'date_debut' => '2027-01-05',
        'date_fin' => '2027-03-05',
        'recurrent' => true,
        'etablissement_ferme' => true,
        'affecte_cours' => true,
        'affecte_personnel' => true,
        'statut' => 'actif',
    ]);
    $calendar = app(AcademicCalendarContract::class);

    expect($calendar->exceptionsOn(new DateTimeImmutable('2027-01-04')))->toBeEmpty()
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2027-01-05')))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2027-02-15')))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2027-03-05')))->toHaveCount(1)
        ->and($calendar->exceptionsOn(new DateTimeImmutable('2027-03-06')))->toBeEmpty();
});
