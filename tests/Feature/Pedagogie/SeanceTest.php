<?php

use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Exceptions\TransitionStatutSeanceInvalideException;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Seance;
use Carbon\Carbon;

beforeEach(function () {
    $this->service ??= app(SeanceServiceInterface::class);

    $matiere = Matiere::create(['nom' => 'Français', 'code' => 'FR', 'coefficient' => 2, 'actif' => true]);

    // jour_semaine = 1 correspond au lundi, comme now()->next(Carbon::MONDAY) plus bas.
    $creneau = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00']);

    $this->emploiDuTemps = EmploiDuTemps::create([
        'classe_id' => 1, 'matiere_id' => $matiere->id, 'enseignant_id' => 10,
        'salle_id' => 100, 'creneau_id' => $creneau->id, 'annee_scolaire_id' => 1, 'actif' => true,
    ]);
});

it('génère les séances de la semaine sans doublon si relancé deux fois', function () {
    $lundi = now()->next(Carbon::MONDAY);

    $this->service->genererSeancesPourSemaine(1, $lundi);
    $this->service->genererSeancesPourSemaine(1, $lundi);

    expect(Seance::where('emploi_du_temps_id', $this->emploiDuTemps->id)->count())->toBe(1);
});

it('refuse de marquer dispensée une séance déjà annulée', function () {
    $seance = Seance::create([
        'emploi_du_temps_id' => $this->emploiDuTemps->id,
        'date_seance' => now()->toDateString(),
        'enseignant_id' => 10,
        'statut' => 'annulee',
    ]);

    expect(fn () => $this->service->marquerDispensee($seance->id))
        ->toThrow(TransitionStatutSeanceInvalideException::class);
});

it('reporter crée une nouvelle séance programmée liée au même cours théorique', function () {
    $seance = Seance::create([
        'emploi_du_temps_id' => $this->emploiDuTemps->id,
        'date_seance' => now()->toDateString(),
        'enseignant_id' => 10,
        'statut' => 'programmee',
    ]);

    $nouvelleDate = now()->addWeek();
    $this->service->reporter($seance->id, $nouvelleDate);

    expect($seance->fresh()->statut)->toBe('reportee');

    $nouvelle = Seance::where('emploi_du_temps_id', $this->emploiDuTemps->id)
        ->where('statut', 'programmee')->first();

    expect($nouvelle)->not->toBeNull()
        ->and($nouvelle->date_seance->toDateString())->toBe($nouvelleDate->toDateString());
});
