<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Contracts\AffectationPedagogiqueServiceInterface;
use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Exceptions\ConflitEmploiDuTempsException;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Cycle;
use App\Modules\Socle\Models\Niveau;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->annee = AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'statut' => 'active',
    ]);

    $this->cyclePrimaire = Cycle::create(['nom' => 'Primaire', 'code' => 'PRI', 'ordre' => 1]);
    $this->niveauCE2 = Niveau::create(['nom' => 'CE2', 'code' => 'CE2', 'cycle_id' => $this->cyclePrimaire->id, 'ordre' => 3]);
    $this->classeCE2 = Classe::create([
        'nom' => 'CE2 A',
        'code' => 'CE2-A',
        'niveau_id' => $this->niveauCE2->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 30,
    ]);

    $this->cycleCollege = Cycle::create(['nom' => 'Collège', 'code' => 'COL', 'ordre' => 2]);
    $this->niveau5e = Niveau::create(['nom' => '5ème', 'code' => '5EME', 'cycle_id' => $this->cycleCollege->id, 'ordre' => 7]);
    $this->classe5e = Classe::create([
        'nom' => '5ème B',
        'code' => '5E-B',
        'niveau_id' => $this->niveau5e->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 32,
    ]);

    $this->matiere = app(MatiereServiceInterface::class)->creer(nom: 'Sciences et Techno', code: 'SVT-5', coefficient: 2.0);

    $this->creneau1 = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00']);
    $this->creneau2 = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '10:00']);

    $this->emploiService = app(EmploiDuTempsServiceInterface::class);
    $this->affectationService = app(AffectationPedagogiqueServiceInterface::class);
});

it('peut planifier une activite sans matiere_id formelle avec un libelle', function (): void {
    $cours = $this->emploiService->planifierCours([
        'classe_id' => $this->classeCE2->id,
        'matiere_id' => null,
        'libelle_activite' => 'Accueil du matin et rituels',
        'enseignant_id' => 10,
        'salle_id' => 1,
        'creneau_id' => $this->creneau1->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]);

    expect($cours)->toBeInstanceOf(EmploiDuTemps::class)
        ->and($cours->matiere_id)->toBeNull()
        ->and($cours->libelle_activite)->toBe('Accueil du matin et rituels');
});

it('deduit automatiquement l enseignant principal en primaire si enseignant_id est omis', function (): void {
    $enseignantPrincipalId = 25;
    $this->affectationService->affecterEnseignantPrincipal(
        enseignantId: $enseignantPrincipalId,
        classeId: $this->classeCE2->id,
        anneeScolaireId: $this->annee->id
    );

    // Planifier sans enseignant_id spécifié
    $cours = $this->emploiService->planifierCours([
        'classe_id' => $this->classeCE2->id,
        'matiere_id' => null,
        'libelle_activite' => 'Calcul mental et écriture',
        'salle_id' => 1,
        'creneau_id' => $this->creneau1->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]);

    expect($cours->enseignant_id)->toBe($enseignantPrincipalId);
});

it('deduit l enseignant affecte a la matiere au secondaire si enseignant_id est omis', function (): void {
    $enseignantSVTId = 42;
    $this->affectationService->affecterEnseignantMatiere(
        enseignantId: $enseignantSVTId,
        classeId: $this->classe5e->id,
        matiereId: $this->matiere['id'],
        anneeScolaireId: $this->annee->id
    );

    $cours = $this->emploiService->planifierCours([
        'classe_id' => $this->classe5e->id,
        'matiere_id' => $this->matiere['id'],
        'salle_id' => 2,
        'creneau_id' => $this->creneau2->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]);

    expect($cours->enseignant_id)->toBe($enseignantSVTId);
});

it('detecte les conflits d enseignant, de salle et de classe sur le meme creneau', function (): void {
    $this->emploiService->planifierCours([
        'classe_id' => $this->classe5e->id,
        'matiere_id' => $this->matiere['id'],
        'enseignant_id' => 10,
        'salle_id' => 5,
        'creneau_id' => $this->creneau1->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]);

    // Conflit enseignant
    expect(fn () => $this->emploiService->planifierCours([
        'classe_id' => $this->classeCE2->id,
        'matiere_id' => null,
        'enseignant_id' => 10,
        'salle_id' => 8,
        'creneau_id' => $this->creneau1->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]))->toThrow(ConflitEmploiDuTempsException::class);

    // Conflit salle
    expect(fn () => $this->emploiService->planifierCours([
        'classe_id' => $this->classeCE2->id,
        'matiere_id' => null,
        'enseignant_id' => 20,
        'salle_id' => 5,
        'creneau_id' => $this->creneau1->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]))->toThrow(ConflitEmploiDuTempsException::class);

    // Conflit classe
    expect(fn () => $this->emploiService->planifierCours([
        'classe_id' => $this->classe5e->id,
        'matiere_id' => $this->matiere['id'],
        'enseignant_id' => 30,
        'salle_id' => 9,
        'creneau_id' => $this->creneau1->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]))->toThrow(ConflitEmploiDuTempsException::class);
});
