<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Exceptions\SeanceNonDispenseeException;
use App\Modules\Pedagogie\Exceptions\TransitionStatutSeanceInvalideException;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Seance;

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

it('empêche de saisir une progression sur une séance non dispensée', function () {
    $seance = creerSeanceDeTest(statut: 'programmee');

    expect(fn () => app(ProgressionServiceInterface::class)
        ->saisirProgression($seance->id, null, 'Chapitre 1'))
        ->toThrow(SeanceNonDispenseeException::class);
});

it('permet de saisir une progression une fois la séance dispensée', function () {
    $seance = creerSeanceDeTest(statut: 'commencee');

    app(SeanceServiceInterface::class)->marquerDispensee($seance->id);

    $progression = app(ProgressionServiceInterface::class)
        ->saisirProgression($seance->id, null, 'Révolution française');

    expect($progression->contenu_couvert)->toBe('Révolution française');
    expect($seance->fresh()->progression_renseignee)->toBeTrue();
});

it('respecte la machine à états des séances', function () {
    $seance = creerSeanceDeTest(statut: 'dispensee');

    expect(fn () => app(SeanceServiceInterface::class)->marquerCommencee($seance->id))
        ->toThrow(TransitionStatutSeanceInvalideException::class);
});
