<?php

declare(strict_types=1);

use App\Modules\Socle\Models\ConfigEtablissement;
use App\Modules\Socle\Models\JourFerie;
use App\Modules\VieScolaire\Contracts\InstitutionalCalendarServiceInterface;

it('ferme l établissement pendant une exception du calendrier', function (): void {
    ConfigEtablissement::query()->create(['nom' => 'Ambassadors', 'jours_ouvrables' => [1, 2, 3, 4, 5, 6]]);
    JourFerie::query()->create([
        'libelle' => 'Fermeture exceptionnelle', 'type' => 'fermeture_exceptionnelle',
        'date' => '2026-09-14', 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-14',
        'affecte_cours' => true, 'affecte_personnel' => true, 'etablissement_ferme' => true, 'statut' => 'actif',
    ]);

    $service = app(InstitutionalCalendarServiceInterface::class);

    expect($service->isSchoolOpen(new DateTimeImmutable('2026-09-14')))->toBeFalse()
        ->and($service->isTeachingDay(new DateTimeImmutable('2026-09-14')))->toBeFalse()
        ->and($service->affectsStaff(new DateTimeImmutable('2026-09-14')))->toBeTrue()
        ->and($service->affectsStudents(new DateTimeImmutable('2026-09-14')))->toBeTrue();
});

it('considère un jour ouvrable sans exception comme enseignable', function (): void {
    ConfigEtablissement::query()->create(['nom' => 'Ambassadors', 'jours_ouvrables' => [1, 2, 3, 4, 5, 6]]);
    $service = app(InstitutionalCalendarServiceInterface::class);

    expect($service->isSchoolOpen(new DateTimeImmutable('2026-09-15')))->toBeTrue()
        ->and($service->isTeachingDay(new DateTimeImmutable('2026-09-15')))->toBeTrue();
});
