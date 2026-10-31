<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\Seance;
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
    $this->niveau4e = Niveau::create(['nom' => '4ème', 'code' => '4EME', 'cycle_id' => $this->cycleCollege->id, 'ordre' => 8]);
    $this->classe4e = Classe::create([
        'nom' => '4ème A',
        'code' => '4E-A',
        'niveau_id' => $this->niveau4e->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 30,
    ]);

    $this->matiere = app(MatiereServiceInterface::class)->creer(nom: 'Physique-Chimie', code: 'PC-4', coefficient: 2.0);

    $this->creneau = CreneauHoraire::create(['jour_semaine' => 2, 'heure_debut' => '10:00', 'heure_fin' => '11:00']);

    $this->cours = EmploiDuTemps::create([
        'classe_id' => $this->classe4e->id,
        'matiere_id' => $this->matiere['id'],
        'enseignant_id' => 10,
        'salle_id' => 1,
        'creneau_id' => $this->creneau->id,
        'annee_scolaire_id' => $this->annee->id,
        'actif' => true,
    ]);

    $this->seanceService = app(SeanceServiceInterface::class);
    $this->presenceService = app(PresenceServiceInterface::class);
    $this->programmeService = app(ProgrammeServiceInterface::class);
});

it('enregistre un remplacement ponctuel d enseignant et conserve l historique', function (): void {
    $seance = Seance::create([
        'emploi_du_temps_id' => $this->cours->id,
        'date_seance' => '2026-09-15',
        'enseignant_id' => 10,
        'statut' => 'programmee',
    ]);

    expect($seance->estRemplacee())->toBeFalse();

    $remplacement = $this->seanceService->remplacerEnseignant(
        seanceId: $seance->id,
        nouveauEnseignantId: 22,
        motif: 'Congé maladie de l’enseignant titulaire'
    );

    $seanceFresh = $seance->fresh();
    expect($seanceFresh->enseignant_prevu_id)->toBe(10)
        ->and($seanceFresh->enseignant_id)->toBe(22)
        ->and($seanceFresh->enseignant_reel_id)->toBe(22)
        ->and($seanceFresh->motif_remplacement)->toBe('Congé maladie de l’enseignant titulaire')
        ->and($seanceFresh->estRemplacee())->toBeTrue();
});

it('renseigne les details d une seance et la lie a une lecon du programme', function (): void {
    $programme = Programme::create([
        'matiere_id' => $this->matiere['id'],
        'niveau_id' => $this->niveau4e->id,
        'annee_scolaire_id' => $this->annee->id,
        'source' => 'officiel',
        'titre' => 'Programme PC 4e',
        'statut' => 'valide',
    ]);
    $chapitre = ProgrammeChapitre::create(['programme_id' => $programme->id, 'titre' => 'L’air qui nous entoure', 'ordre' => 1]);
    $lecon = $this->programmeService->ajouterLecon(
        chapitreId: $chapitre->id,
        titre: 'Composition et masse de l’air',
        dureeHeures: 1.0,
        ordre: 1
    );

    $seance = Seance::create([
        'emploi_du_temps_id' => $this->cours->id,
        'date_seance' => '2026-09-15',
        'enseignant_id' => 10,
        'statut' => 'commencee',
    ]);

    $this->seanceService->renseignerSeance($seance->id, [
        'programme_lecon_id' => $lecon->id,
        'contenu_enseigne' => 'Expérience sur la masse de 1L d’air à pression ambiante',
        'devoirs_donnes' => 'Fiche bilan d’activité à compléter',
        'duree_minutes' => 55,
        'salle_id' => 3, // salle de TP
    ]);

    $seanceFresh = $seance->fresh();
    expect($seanceFresh->programme_lecon_id)->toBe($lecon->id)
        ->and($seanceFresh->contenu_enseigne)->toContain('Expérience sur la masse')
        ->and($seanceFresh->devoirs_donnes)->toContain('Fiche bilan')
        ->and($seanceFresh->duree_minutes)->toBe(55)
        ->and($seanceFresh->salle_id)->toBe(3);
});

it('enregistre l appel avec differents statuts dont dispense et verrouille la modification', function (): void {
    $seance = Seance::create([
        'emploi_du_temps_id' => $this->cours->id,
        'date_seance' => '2026-09-15',
        'enseignant_id' => 10,
        'statut' => 'programmee',
    ]);

    // Appel avec structure détaillée
    $this->presenceService->faireAppel($seance->id, [
        ['eleve_id' => 101, 'statut' => 'present'],
        ['eleve_id' => 102, 'statut' => 'absent', 'motif' => 'Non justifié'],
        ['eleve_id' => 103, 'statut' => 'retard', 'heure_arrivee' => '10:15'],
        ['eleve_id' => 104, 'statut' => 'dispense', 'motif' => 'Certificat médical'],
    ]);

    expect($seance->fresh()->statut)->toBe('dispensee');
    expect($seance->presences()->count())->toBe(4);

    $presenceDispensee = $seance->presences()->where('eleve_id', 104)->first();
    expect($presenceDispensee->statut)->toBe('dispense')
        ->and($presenceDispensee->motif)->toBe('Certificat médical');

    // Verrouillage de l'appel
    expect($this->presenceService->estAppelVerrouille($seance->id))->toBeFalse();
    $this->presenceService->verrouillerAppel($seance->id);
    expect($this->presenceService->estAppelVerrouille($seance->id))->toBeTrue();

    // Tentative de modification après verrouillage interdite
    expect(fn () => $this->presenceService->enregistrerPointage(101, $seance->id, 'absent'))
        ->toThrow(DomainException::class);
});
