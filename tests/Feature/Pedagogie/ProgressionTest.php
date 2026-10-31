<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Exceptions\SeanceNonDispenseeException;
use App\Modules\Pedagogie\Exceptions\TransitionStatutSeanceInvalideException;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Pedagogie\Services\ProgressionCalculator;

// EmploiDuTempsService dépend de RH\Contracts\EnseignantServiceInterface,
// non livré (voir README) -> on crée le cours théorique et la séance
// directement via les Models pour isoler ce test de cette dépendance
// bloquante, sans passer par EmploiDuTempsServiceInterface::planifierCours().
function creerSeanceDeTest(string $statut = 'programmee'): Seance
{
    $matiere = app(MatiereServiceInterface::class)->creer(nom: 'Histoire', code: 'HIST-'.uniqid(), coefficient: 2.0);

    $creneau = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00']);

    $cours = EmploiDuTemps::create([
        'classe_id' => 1,
        'matiere_id' => $matiere['id'],
        'enseignant_id' => 1,
        'salle_id' => 1,
        'creneau_id' => $creneau->id,
        'annee_scolaire_id' => 1,
        'actif' => true,
    ]);

    return Seance::create([
        'emploi_du_temps_id' => $cours->id,
        'date_seance' => now()->format('Y-m-d'),
        'enseignant_id' => 1,
        'statut' => $statut,
    ]);
}

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'Fondateur', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('Fondateur');
    $this->actingAs($user);
});

it('empêche de saisir une progression sur une séance non dispensée', function () {
    $seance = creerSeanceDeTest(statut: 'programmee');

    expect(fn () => app(ProgressionServiceInterface::class)
        ->saisirProgression($seance->id, null, 'Chapitre 1'))
        ->toThrow(SeanceNonDispenseeException::class);
});

it('permet de saisir une progression une fois la séance dispensée', function () {
    $seance = creerSeanceDeTest(statut: 'commencee');

    app(SeanceServiceInterface::class)->marquerDispensee($seance->id);

    $programme = Programme::create([
        'matiere_id' => $seance->emploiDuTemps->matiere_id,
        'niveau_id' => 1,
        'classe_id' => $seance->emploiDuTemps->classe_id,
        'annee_scolaire_id' => $seance->emploiDuTemps->annee_scolaire_id,
        'source' => 'officiel',
        'titre' => 'Programme Histoire',
        'statut' => 'valide',
    ]);
    OffrePedagogique::create([
        'matiere_id' => $seance->emploiDuTemps->matiere_id,
        'classe_id' => $seance->emploiDuTemps->classe_id,
        'niveau_id' => 1,
        'annee_scolaire_id' => $seance->emploiDuTemps->annee_scolaire_id,
        'programme_id' => $programme->id,
        'actif' => true,
    ]);
    $chapitre = ProgrammeChapitre::create(['programme_id' => $programme->id, 'titre' => 'Révolution française', 'ordre' => 1, 'contenu' => 'Révolution française']);

    $progression = app(ProgressionServiceInterface::class)
        ->saisirProgression($seance->id, $chapitre->id, 'Révolution française', null, true);

    expect($progression->contenu_couvert)->toBe('Révolution française');
    expect($seance->fresh()->progression_renseignee)->toBeTrue();
    expect($chapitre->fresh()->statut)->not->toBe('traite');
    expect($progression->chapitre_termine)->toBeTrue();
});

it('respecte la machine à états des séances', function () {
    $seance = creerSeanceDeTest(statut: 'dispensee');

    expect(fn () => app(SeanceServiceInterface::class)->marquerCommencee($seance->id))
        ->toThrow(TransitionStatutSeanceInvalideException::class);
});

it('calcule uniquement les éléments terminés dans la classe et année demandées', function () {
    $seance = creerSeanceDeTest('dispensee');
    $programme = Programme::create(['matiere_id' => $seance->emploiDuTemps->matiere_id, 'niveau_id' => 1, 'annee_scolaire_id' => 1, 'titre' => 'Commun', 'statut' => 'valide']);
    $a = ProgrammeChapitre::create(['programme_id' => $programme->id, 'titre' => 'A', 'ordre' => 1]);
    $b = ProgrammeChapitre::create(['programme_id' => $programme->id, 'titre' => 'B', 'ordre' => 2]);
    $p = Progression::create(['seance_id' => $seance->id, 'chapitre_id' => $a->id, 'contenu_couvert' => 'Début', 'chapitre_termine' => false]);
    $calculator = app(ProgressionCalculator::class);
    expect($calculator->pourChapitres($programme->chapitres, 1, 1)['pourcentage'])->toBe(0.0);
    $p->update(['chapitre_termine' => true]);
    expect($calculator->pourChapitres($programme->chapitres, 1, 1)['pourcentage'])->toBe(50.0);
    expect($calculator->pourChapitres($programme->chapitres, 2, 1)['pourcentage'])->toBe(0.0);
    expect($calculator->pourChapitres($programme->chapitres, 1, 2)['pourcentage'])->toBe(0.0);
    $a->update(['volume_horaire_prevu' => 10]);
    $b->update(['volume_horaire_prevu' => 30]);
    expect($calculator->pourChapitres($programme->chapitres()->get(), 1, 1)['pourcentage'])->toBe(25.0);
    $b->update(['volume_horaire_prevu' => null]);
    expect($calculator->pourChapitres($programme->chapitres()->get(), 1, 1)['pourcentage'])->toBe(50.0);
    $seance->update(['statut' => 'annulee']);
    expect($calculator->pourChapitres($programme->chapitres, 1, 1)['pourcentage'])->toBe(0.0);
});
