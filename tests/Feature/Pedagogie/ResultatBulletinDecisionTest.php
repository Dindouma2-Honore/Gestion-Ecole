<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Contracts\BulletinServiceInterface;
use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Bulletin;
use App\Modules\Pedagogie\Models\DecisionPedagogique;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Cycle;
use App\Modules\Socle\Models\Niveau;
use App\Modules\Socle\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->annee = AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'statut' => 'active',
    ]);

    $this->periode = Periode::create([
        'annee_scolaire_id' => $this->annee->id,
        'nom' => 'Trimestre 1',
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-12-31',
    ]);

    $this->cycleCollege = Cycle::create(['nom' => 'Collège', 'code' => 'COL', 'ordre' => 2]);
    $this->niveau6e = Niveau::create(['nom' => '6ème', 'code' => '6EME', 'cycle_id' => $this->cycleCollege->id, 'ordre' => 6]);
    $this->classe6e = Classe::create([
        'nom' => '6ème A',
        'code' => '6E-A',
        'niveau_id' => $this->niveau6e->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 30,
    ]);

    $this->matiereMaths = app(MatiereServiceInterface::class)->creer(nom: 'Mathématiques', code: 'MATH-6', coefficient: 4.0);
    $this->matiereFrancais = app(MatiereServiceInterface::class)->creer(nom: 'Français', code: 'FRAN-6', coefficient: 3.0);

    // Affectations avec coefficients
    AffectationPedagogique::create([
        'classe_id' => $this->classe6e->id,
        'matiere_id' => $this->matiereMaths['id'],
        'enseignant_id' => 10,
        'annee_scolaire_id' => $this->annee->id,
        'coefficient' => 4.0,
        'actif' => true,
    ]);

    AffectationPedagogique::create([
        'classe_id' => $this->classe6e->id,
        'matiere_id' => $this->matiereFrancais['id'],
        'enseignant_id' => 11,
        'annee_scolaire_id' => $this->annee->id,
        'coefficient' => 3.0,
        'actif' => true,
    ]);

    $this->bulletinService = app(BulletinServiceInterface::class);
    $this->noteService = app(NoteServiceInterface::class);
    $this->evalService = app(EvaluationServiceInterface::class);
});

it('calcule la moyenne generale ponderee, le rang et les statistiques de classe', function (): void {
    // Évaluations de Maths (sur 20)
    $evalMaths = Evaluation::create([
        'titre' => 'Devoir Maths 1',
        'matiere_id' => $this->matiereMaths['id'],
        'classe_id' => $this->classe6e->id,
        'annee_scolaire_id' => $this->annee->id,
        'periode_id' => $this->periode->id,
        'date_evaluation' => now()->subDay()->format('Y-m-d'),
        'bareme' => 20.0,
        'coefficient_evaluation' => 1.0,
        'statut' => 'valide',
    ]);

    // Évaluation de Français (sur 20)
    $evalFrancais = Evaluation::create([
        'titre' => 'Devoir Français 1',
        'matiere_id' => $this->matiereFrancais['id'],
        'classe_id' => $this->classe6e->id,
        'annee_scolaire_id' => $this->annee->id,
        'periode_id' => $this->periode->id,
        'date_evaluation' => now()->subDay()->format('Y-m-d'),
        'bareme' => 20.0,
        'coefficient_evaluation' => 1.0,
        'statut' => 'valide',
    ]);

    // Élève 1 : Maths 16 (coef 4) + Français 14 (coef 3) => (16*4 + 14*3) / 7 = (64 + 42) / 7 = 106 / 7 = 15.14
    $this->noteService->enregistrer($evalMaths->id, 101, 16.0);
    $this->noteService->enregistrer($evalFrancais->id, 101, 14.0);

    // Élève 2 : Maths 10 (coef 4) + Français 12 (coef 3) => (10*4 + 12*3) / 7 = (40 + 36) / 7 = 76 / 7 = 10.86
    $this->noteService->enregistrer($evalMaths->id, 102, 10.0);
    $this->noteService->enregistrer($evalFrancais->id, 102, 12.0);

    // Génération du bulletin de l'élève 101
    $bulletin1 = $this->bulletinService->genererPourEleve(101, $this->classe6e->id, $this->annee->id, $this->periode->id);

    expect($bulletin1)->toBeInstanceOf(Bulletin::class)
        ->and($bulletin1->moyenne_generale)->toEqual(15.14)
        ->and($bulletin1->rang)->toBe(1)
        ->and($bulletin1->effectif_classe)->toBe(2);

    // Génération du bulletin de l'élève 102
    $bulletin2 = $this->bulletinService->genererPourEleve(102, $this->classe6e->id, $this->annee->id, $this->periode->id);

    expect($bulletin2->moyenne_generale)->toEqual(10.86)
        ->and($bulletin2->rang)->toBe(2);

    // Statistiques de classe
    expect($bulletin1->moyenne_plus_forte)->toEqual(15.14)
        ->and($bulletin1->moyenne_plus_faible)->toEqual(10.86);
});

it('enregistre et recupere la decision du conseil de classe pour un eleve', function (): void {
    $decision = $this->bulletinService->enregistrerDecisionConseil(
        eleveId: 101,
        classeId: $this->classe6e->id,
        anneeScolaireId: $this->annee->id,
        decision: 'admis',
        motif: 'Félicitations du conseil, passage en 5ème',
        validePar: 1
    );

    expect($decision)->toBeInstanceOf(DecisionPedagogique::class)
        ->and($decision->decision)->toBe('admis')
        ->and($decision->motif)->toContain('passage en 5ème');

    $recuperee = $this->bulletinService->getDecisionConseil(101, $this->annee->id);
    expect($recuperee)->not->toBeNull()
        ->and($recuperee->decision)->toBe('admis');

    $decisionsClasse = $this->bulletinService->getDecisionsClasse($this->classe6e->id, $this->annee->id);
    expect($decisionsClasse)->toHaveCount(1);
});

it('rejette une decision non conforme a la liste reglementaire', function (): void {
    expect(fn () => $this->bulletinService->enregistrerDecisionConseil(
        eleveId: 101,
        classeId: $this->classe6e->id,
        anneeScolaireId: $this->annee->id,
        decision: 'decision_inconnue'
    ))->toThrow(\InvalidArgumentException::class);
});
