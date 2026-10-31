<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\ProgrammeLecon;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Cycle;
use App\Modules\Socle\Models\Niveau;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'Fondateur', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('Fondateur');
    $this->actingAs($user);

    $this->annee = AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'statut' => 'active',
    ]);

    $this->cycleCollege = Cycle::create(['nom' => 'Collège', 'code' => 'COL', 'ordre' => 2]);
    $this->niveau6e = Niveau::create(['nom' => '6ème', 'code' => '6EME', 'cycle_id' => $this->cycleCollege->id, 'ordre' => 6]);

    $this->classe6eA = Classe::create([
        'nom' => '6ème A',
        'code' => '6E-A',
        'niveau_id' => $this->niveau6e->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 30,
    ]);

    $this->classe6eB = Classe::create([
        'nom' => '6ème B',
        'code' => '6E-B',
        'niveau_id' => $this->niveau6e->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 30,
    ]);

    $this->matiere = app(MatiereServiceInterface::class)->creer(nom: 'Mathématiques', code: 'MATH-6E', coefficient: 4.0);

    $this->programmeService = app(ProgrammeServiceInterface::class);
    $this->progressionService = app(ProgressionServiceInterface::class);
    $this->seanceService = app(SeanceServiceInterface::class);
});

it('peut ajouter des lecons a un chapitre et les lister', function (): void {
    $programme = Programme::create([
        'matiere_id' => $this->matiere['id'],
        'niveau_id' => $this->niveau6e->id,
        'annee_scolaire_id' => $this->annee->id,
        'source' => 'officiel',
        'titre' => 'Programme Maths 6e',
        'statut' => 'valide',
    ]);
    $chapitre = ProgrammeChapitre::create([
        'programme_id' => $programme->id,
        'titre' => 'Nombres entiers et décimaux',
        'ordre' => 1,
    ]);

    $lecon1 = $this->programmeService->ajouterLecon(
        chapitreId: $chapitre->id,
        titre: 'Écriture et lecture des nombres décimaux',
        objectifs: 'Savoir lire et écrire les fractions décimales',
        dureeHeures: 2.0,
        ordre: 1
    );

    $lecon2 = $this->programmeService->ajouterLecon(
        chapitreId: $chapitre->id,
        titre: 'Comparaison et rangement',
        objectifs: 'Ranger les nombres décimaux par ordre croissant',
        dureeHeures: 1.5,
        ordre: 2
    );

    expect($lecon1)->toBeInstanceOf(ProgrammeLecon::class)
        ->and($lecon1->titre)->toBe('Écriture et lecture des nombres décimaux')
        ->and($lecon1->statut)->toBe('a_faire');

    $lecons = $this->programmeService->getLeconsChapitre($chapitre->id);
    expect($lecons)->toHaveCount(2);
    expect($lecons->pluck('titre')->all())->toBe([
        'Écriture et lecture des nombres décimaux',
        'Comparaison et rangement',
    ]);
});

it('enregistre la progression au niveau lecon et met a jour son statut', function (): void {
    $programme = Programme::create([
        'matiere_id' => $this->matiere['id'],
        'niveau_id' => $this->niveau6e->id,
        'classe_id' => $this->classe6eA->id,
        'annee_scolaire_id' => $this->annee->id,
        'source' => 'officiel',
        'titre' => 'Programme Maths 6e A',
        'statut' => 'valide',
    ]);
    OffrePedagogique::create([
        'matiere_id' => $this->matiere['id'],
        'classe_id' => $this->classe6eA->id,
        'niveau_id' => $this->niveau6e->id,
        'annee_scolaire_id' => $this->annee->id,
        'programme_id' => $programme->id,
        'actif' => true,
    ]);

    $chapitre = ProgrammeChapitre::create([
        'programme_id' => $programme->id,
        'titre' => 'Géométrie plane',
        'ordre' => 1,
    ]);

    $lecon = $this->programmeService->ajouterLecon(
        chapitreId: $chapitre->id,
        titre: 'Droites perpendiculaires et parallèles',
        dureeHeures: 2.0,
        ordre: 1
    );

    $creneau = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00']);

    $cours = EmploiDuTemps::create([
        'classe_id' => $this->classe6eA->id,
        'matiere_id' => $this->matiere['id'],
        'enseignant_id' => 10,
        'salle_id' => 1,
        'creneau_id' => $creneau->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]);

    $seance = Seance::create([
        'emploi_du_temps_id' => $cours->id,
        'date_seance' => now()->format('Y-m-d'),
        'enseignant_id' => 10,
        'statut' => 'commencee',
    ]);

    $this->seanceService->marquerDispensee($seance->id);

    $progression = $this->progressionService->saisirProgression(
        seanceId: $seance->id,
        chapitreId: $chapitre->id,
        contenu: 'Tracé de droites perpendiculaires à la règle et à l’équerre',
        devoirs: 'Exercices 3 et 4 page 42',
        chapitreTermine: false,
        leconId: $lecon->id
    );

    expect($progression->lecon_id)->toBe($lecon->id);
    expect($lecon->fresh()->statut)->toBe('terminee');
    expect($chapitre->fresh()->statut)->toBe('en_cours');
});

it('compare la progression pedagogique de toutes les classes d un niveau pour une matiere', function (): void {
    // Créer programme officiel pour 6e A
    $progA = Programme::create([
        'matiere_id' => $this->matiere['id'],
        'niveau_id' => $this->niveau6e->id,
        'classe_id' => $this->classe6eA->id,
        'annee_scolaire_id' => $this->annee->id,
        'source' => 'officiel',
        'titre' => 'Maths 6e A',
        'statut' => 'valide',
    ]);
    OffrePedagogique::create([
        'matiere_id' => $this->matiere['id'], 'classe_id' => $this->classe6eA->id,
        'niveau_id' => $this->niveau6e->id, 'annee_scolaire_id' => $this->annee->id,
        'programme_id' => $progA->id, 'actif' => true,
    ]);
    $chapA1 = ProgrammeChapitre::create(['programme_id' => $progA->id, 'titre' => 'Chapitre 1', 'ordre' => 1]);
    $chapA2 = ProgrammeChapitre::create(['programme_id' => $progA->id, 'titre' => 'Chapitre 2', 'ordre' => 2]);

    // Les deux offres réutilisent le même programme de référence.
    $progB = $progA;
    OffrePedagogique::create([
        'matiere_id' => $this->matiere['id'], 'classe_id' => $this->classe6eB->id,
        'niveau_id' => $this->niveau6e->id, 'annee_scolaire_id' => $this->annee->id,
        'programme_id' => $progB->id, 'actif' => true,
    ]);
    $chapB1 = $chapA1;
    $chapB2 = $chapA2;

    $creneau = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00']);

    // Cours et séances pour 6e A (couvre 2 chapitres sur 2 = 100%)
    $coursA = EmploiDuTemps::create([
        'classe_id' => $this->classe6eA->id,
        'matiere_id' => $this->matiere['id'],
        'enseignant_id' => 10,
        'salle_id' => 1,
        'creneau_id' => $creneau->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]);
    $seanceA1 = Seance::create(['emploi_du_temps_id' => $coursA->id, 'date_seance' => '2026-09-10', 'enseignant_id' => 10, 'statut' => 'commencee']);
    $this->seanceService->marquerDispensee($seanceA1->id);
    $this->progressionService->saisirProgression($seanceA1->id, $chapA1->id, 'Contenu 1', null, true);

    $seanceA2 = Seance::create(['emploi_du_temps_id' => $coursA->id, 'date_seance' => '2026-09-15', 'enseignant_id' => 10, 'statut' => 'commencee']);
    $this->seanceService->marquerDispensee($seanceA2->id);
    $this->progressionService->saisirProgression($seanceA2->id, $chapA2->id, 'Contenu 2', null, true);

    // Cours et séance pour 6e B (couvre 1 chapitre sur 2 = 50%)
    $coursB = EmploiDuTemps::create([
        'classe_id' => $this->classe6eB->id,
        'matiere_id' => $this->matiere['id'],
        'enseignant_id' => 11,
        'salle_id' => 2,
        'creneau_id' => $creneau->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]);
    $seanceB1 = Seance::create(['emploi_du_temps_id' => $coursB->id, 'date_seance' => '2026-09-10', 'enseignant_id' => 11, 'statut' => 'commencee']);
    $this->seanceService->marquerDispensee($seanceB1->id);
    $this->progressionService->saisirProgression($seanceB1->id, $chapB1->id, 'Contenu 1 B', null, true);

    // Comparaison au niveau 6e
    $comparaison = $this->progressionService->comparerProgressionNiveau(
        matiereId: $this->matiere['id'],
        niveauId: $this->niveau6e->id,
        anneeScolaireId: $this->annee->id
    );

    expect($comparaison['niveau_id'])->toBe($this->niveau6e->id);
    expect($comparaison['matiere_id'])->toBe($this->matiere['id']);
    expect($comparaison['classes'])->toHaveCount(2);

    $classeAData = collect($comparaison['classes'])->firstWhere('classe_id', $this->classe6eA->id);
    $classeBData = collect($comparaison['classes'])->firstWhere('classe_id', $this->classe6eB->id);

    expect($classeAData['progression'])->toEqual(100.0);
    expect($classeAData['chapitres_termines'])->toBe(2);
    expect($classeBData['progression'])->toEqual(50.0);
    expect($classeBData['chapitres_termines'])->toBe(1);

    // Moyenne du niveau : (100 + 50) / 2 = 75%
    expect($comparaison['moyenne_niveau'])->toEqual(75.0);
});
