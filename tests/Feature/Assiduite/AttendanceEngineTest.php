<?php

declare(strict_types=1);

use App\Modules\Assiduite\Contracts\AttendanceEngineInterface;
use App\Modules\Assiduite\Models\AnomalieAssiduite;
use App\Modules\Assiduite\Models\JustificationAssiduite;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Contracts\AcademicCalendarContract;
use App\Modules\Socle\Models\ConfigEtablissement;
use Illuminate\Support\Facades\DB;

function creerEmployeAssiduite(string $matricule): Employe
{
    return Employe::query()->create([
        'matricule' => $matricule,
        'nom' => 'Personnel',
        'prenom' => 'Test',
        'poste' => 'Enseignant',
        'date_embauche' => '2026-01-01',
        'statut' => 'actif',
    ]);
}

it('ne demande aucune présence au personnel lorsque l établissement est fermé', function (): void {
    $calendrier = Mockery::mock(AcademicCalendarContract::class);
    $calendrier->shouldReceive('isSchoolOpen')->once()->andReturnFalse();
    app()->instance(AcademicCalendarContract::class, $calendrier);

    $resultat = app(AttendanceEngineInterface::class)->calculer('personnel', 999, new DateTimeImmutable('2026-09-14'));

    expect($resultat->statut)->toBe('non_attendu')
        ->and($resultat->source)->toBe('calendrier');
});

it('calcule le retard et signale un pointage de sortie manquant', function (): void {
    $employe = creerEmployeAssiduite('ASS-001');
    ConfigEtablissement::query()->create([
        'nom' => 'Ambassadors',
        'heure_ouverture' => '07:30:00',
        'tolerance_retard_minutes' => 10,
        'jours_ouvrables' => [1, 2, 3, 4, 5, 6],
    ]);
    DB::table('pointages')->insert([
        'employe_id' => $employe->id,
        'date_pointage' => '2026-09-14',
        'heure_arrivee' => '08:00:00',
        'heure_depart' => null,
        'mode_pointage' => 'manuel',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $calendrier = Mockery::mock(AcademicCalendarContract::class);
    $calendrier->shouldReceive('isSchoolOpen')->once()->andReturnTrue();
    app()->instance(AcademicCalendarContract::class, $calendrier);

    $resultat = app(AttendanceEngineInterface::class)->calculer('personnel', $employe->id, new DateTimeImmutable('2026-09-14'));

    expect($resultat->statut)->toBe('retard')
        ->and($resultat->minutes_retard)->toBe(30)
        ->and(AnomalieAssiduite::query()->where('type', 'MISSING_EXIT_PUNCH')->where('personne_id', $employe->id)->exists())->toBeTrue();
});

it('répercute une justification acceptée sur le statut journalier', function (): void {
    $employe = creerEmployeAssiduite('ASS-002');
    JustificationAssiduite::query()->create([
        'personne_type' => 'personnel',
        'personne_id' => $employe->id,
        'type' => 'absence',
        'date_concernee' => '2026-09-14',
        'motif' => 'Rendez-vous médical',
        'soumis_par' => 1,
        'soumis_le' => now(),
        'decision' => 'acceptee',
    ]);
    $calendrier = Mockery::mock(AcademicCalendarContract::class);
    $calendrier->shouldReceive('isSchoolOpen')->once()->andReturnTrue();
    app()->instance(AcademicCalendarContract::class, $calendrier);

    $resultat = app(AttendanceEngineInterface::class)->calculer('personnel', $employe->id, new DateTimeImmutable('2026-09-14'));

    expect($resultat->statut)->toBe('absence_justifiee');
});
