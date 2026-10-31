<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Exceptions\NoteInvalideException;
use App\Modules\Pedagogie\Exceptions\NoteModificationVerrouilleeException;
use App\Modules\Pedagogie\Exceptions\SujetExamenNonValideException;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->annee = AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'statut' => 'active',
    ]);

    $this->cycleCollege = Cycle::create(['nom' => 'Collège', 'code' => 'COL', 'ordre' => 2]);
    $this->niveau3e = Niveau::create(['nom' => '3ème', 'code' => '3EME', 'cycle_id' => $this->cycleCollege->id, 'ordre' => 9]);
    $this->classe3e = Classe::create([
        'nom' => '3ème A',
        'code' => '3E-A',
        'niveau_id' => $this->niveau3e->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 30,
    ]);

    $this->matiere = app(MatiereServiceInterface::class)->creer(nom: 'Français', code: 'FRAN-3', coefficient: 4.0);

    $this->evaluationService = app(EvaluationServiceInterface::class);
    $this->noteService = app(NoteServiceInterface::class);
});

it('exige la validation du sujet avant toute saisie de note', function (): void {
    $eval = Evaluation::create([
        'titre' => 'Devoir surveillé 1',
        'matiere_id' => $this->matiere['id'],
        'classe_id' => $this->classe3e->id,
        'annee_scolaire_id' => $this->annee->id,
        'date_evaluation' => now()->subDay()->format('Y-m-d'),
        'bareme' => 20.0,
        'statut' => 'soumis',
    ]);

    expect(fn () => $this->noteService->enregistrer($eval->id, 101, 15.5))
        ->toThrow(SujetExamenNonValideException::class);

    // Valider le sujet
    $this->evaluationService->validerSujet($eval->id, 1);
    expect($eval->fresh()->estValidee())->toBeTrue();

    $note = $this->noteService->enregistrer($eval->id, 101, 15.5);
    expect($note)->toBeInstanceOf(Note::class)
        ->and($note->valeur)->toBe(15.5);
});

it('respecte un bareme dynamique personnalise', function (): void {
    $eval = Evaluation::create([
        'titre' => 'Interrogation sur 40',
        'matiere_id' => $this->matiere['id'],
        'classe_id' => $this->classe3e->id,
        'annee_scolaire_id' => $this->annee->id,
        'date_evaluation' => now()->subDay()->format('Y-m-d'),
        'bareme' => 40.0,
        'statut' => 'valide',
    ]);

    // Note valide jusqu'à 40
    $note = $this->noteService->enregistrer($eval->id, 101, 38.5);
    expect($note->valeur)->toBe(38.5);

    // Rejette si supérieur au barème 40
    expect(fn () => $this->noteService->enregistrer($eval->id, 102, 41.0))
        ->toThrow(NoteInvalideException::class);
});

it('enregistre un lot de notes pour une classe et calcule les statistiques', function (): void {
    $eval = Evaluation::create([
        'titre' => 'Composition Trimestre 1',
        'matiere_id' => $this->matiere['id'],
        'classe_id' => $this->classe3e->id,
        'annee_scolaire_id' => $this->annee->id,
        'date_evaluation' => now()->subDay()->format('Y-m-d'),
        'bareme' => 20.0,
        'statut' => 'valide',
    ]);

    $lot = [
        ['eleve_id' => 201, 'valeur' => 16.0, 'statut_presence' => 'present', 'appreciation' => 'Très bon devoir'],
        ['eleve_id' => 202, 'valeur' => 12.0, 'statut_presence' => 'present', 'appreciation' => 'Assez bien'],
        ['eleve_id' => 203, 'valeur' => null, 'statut_presence' => 'absent_justifie', 'appreciation' => 'Maladie'],
        ['eleve_id' => 204, 'valeur' => null, 'statut_presence' => 'absent_injustifie'], // comptera 0.0
        ['eleve_id' => 205, 'valeur' => null, 'statut_presence' => 'dispense', 'competence_niveau' => 'acquis'],
    ];

    $resultat = $this->noteService->enregistrerLot($eval->id, $lot);

    expect($resultat['nb_saisies'])->toBe(5)
        ->and($resultat['nb_absents'])->toBe(3);

    // Notes prises en compte dans la moyenne :
    // 201: 16.0, 202: 12.0, 204 (injustifié): 0.0 => (16 + 12 + 0) / 3 = 9.33
    expect($resultat['moyenne'])->toBe(9.33);

    // Vérifier les enregistrements en base
    $noteJustifiee = Note::where('evaluation_id', $eval->id)->where('eleve_id', 203)->first();
    expect($noteJustifiee->valeur)->toBeNull()
        ->and($noteJustifiee->statut_presence)->toBe('absent_justifie')
        ->and($noteJustifiee->absent)->toBeTrue();

    $noteDispensee = Note::where('evaluation_id', $eval->id)->where('eleve_id', 205)->first();
    expect($noteDispensee->competence_niveau)->toBe('acquis')
        ->and($noteDispensee->statut_presence)->toBe('dispense');
});

it('bloque la modification une fois l evaluation verrouillee', function (): void {
    $eval = Evaluation::create([
        'titre' => 'Examen blanc',
        'matiere_id' => $this->matiere['id'],
        'classe_id' => $this->classe3e->id,
        'annee_scolaire_id' => $this->annee->id,
        'date_evaluation' => now()->subDay()->format('Y-m-d'),
        'bareme' => 20.0,
        'statut' => 'valide',
    ]);

    $this->noteService->enregistrer($eval->id, 301, 14.0);

    // Verrouillage de l'évaluation
    $this->noteService->verrouillerEvaluation($eval->id);
    expect($eval->fresh()->verrouillee)->toBeTrue();

    // Tentative de modification refusée
    expect(fn () => $this->noteService->enregistrer($eval->id, 301, 18.0))
        ->toThrow(NoteModificationVerrouilleeException::class);

    expect(fn () => $this->noteService->enregistrerLot($eval->id, [
        ['eleve_id' => 302, 'valeur' => 10.0],
    ]))->toThrow(NoteModificationVerrouilleeException::class);
});
