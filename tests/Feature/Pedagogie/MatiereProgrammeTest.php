<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Exceptions\CodeMatiereDejaUtiliseException;
use App\Modules\Pedagogie\Exceptions\MatiereIntrouvableException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['statut' => 'actif']));

    $this->anneeScolaire = AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => now(),
        'date_fin' => now()->addMonths(10),
        'statut' => 'active',
    ]);
});

it('crée une matière', function () {
    $service = app(MatiereServiceInterface::class);

    $matiere = $service->creer(nom: 'Mathématiques', code: 'MATH', coefficient: 4.0);

    expect($matiere['nom'])->toBe('Mathématiques')
        ->and($matiere['code'])->toBe('MATH');
});

it('empêche deux matières avec le même code', function () {
    $service = app(MatiereServiceInterface::class);

    $service->creer(nom: 'Anglais', code: 'ANG', coefficient: 2.0);

    expect(fn () => $service->creer(nom: 'Anglais renforcé', code: 'ANG', coefficient: 1.0))
        ->toThrow(CodeMatiereDejaUtiliseException::class);
});

it('lève une exception si la matière n\'existe pas', function () {
    $service = app(MatiereServiceInterface::class);

    expect(fn () => $service->getMatiere(999))
        ->toThrow(MatiereIntrouvableException::class);
});

it('crée un programme rattaché à une matière existante', function () {
    $matiereService = app(MatiereServiceInterface::class);
    $programmeService = app(ProgrammeServiceInterface::class);

    $matiere = $matiereService->creer(nom: 'Physique-Chimie', code: 'PC', coefficient: 3.0);

    $programme = $programmeService->creerProgramme(
        matiereId: $matiere['id'],
        niveauId: 1,
        anneeScolaireId: $this->anneeScolaire->id,
        titre: 'Programme Physique-Chimie 2026-2027',
    );

    expect($programme['statut'])->toBe('brouillon');
});

it('empêche de créer un programme pour une matière inexistante', function () {
    $programmeService = app(ProgrammeServiceInterface::class);

    expect(fn () => $programmeService->creerProgramme(
        matiereId: 999,
        niveauId: 1,
        anneeScolaireId: $this->anneeScolaire->id,
        titre: 'Programme fantôme',
    ))->toThrow(MatiereIntrouvableException::class);
});

it('publie un programme', function () {
    $matiereService = app(MatiereServiceInterface::class);
    $programmeService = app(ProgrammeServiceInterface::class);

    $matiere = $matiereService->creer(nom: 'SVT', code: 'SVT', coefficient: 2.0);
    $programme = $programmeService->creerProgramme(
        matiereId: $matiere['id'],
        niveauId: 1,
        anneeScolaireId: $this->anneeScolaire->id,
        titre: 'Programme SVT',
    );

    $publie = $programmeService->publier($programme['id']);

    expect($publie['statut'])->toBe('publie');
});