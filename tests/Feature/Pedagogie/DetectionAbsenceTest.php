<?php

use App\Modules\Pedagogie\Contracts\DetectionAbsenceServiceInterface;
use App\Modules\Pedagogie\Models\AnomalieAppel;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Presence;
use App\Modules\Pedagogie\Models\Seance;

beforeEach(function () {
    $this->service ??= app(DetectionAbsenceServiceInterface::class);

    $matiere = Matiere::create(['nom' => 'SVT', 'code' => 'SVT', 'coefficient' => 1, 'actif' => true]);

    // Heure fixe (minuit) plutôt que "now() - 2h" : évite tout passage de
    // minuit qui inverserait la comparaison de chaînes heure_debut <= seuil.
    // Ne casse que si le test tourne dans les 15 premières minutes après minuit.
    $creneau = CreneauHoraire::create([
        'jour_semaine' => now()->isoWeekday(),
        'heure_debut' => '00:00:00',
        'heure_fin' => '01:00:00',
    ]);

    $emploiDuTemps = EmploiDuTemps::create([
        'classe_id' => 1, 'matiere_id' => $matiere->id, 'enseignant_id' => 10,
        'salle_id' => 100, 'creneau_id' => $creneau->id, 'annee_scolaire_id' => 1, 'actif' => true,
    ]);

    $this->seance = Seance::create([
        'emploi_du_temps_id' => $emploiDuTemps->id,
        'date_seance' => now()->toDateString(),
        'enseignant_id' => 10,
        'statut' => 'programmee',
    ]);
});

it('crée une anomalie pour une séance sans appel après le délai de tolérance', function () {
    $anomalies = $this->service->detecterAnomalies();

    expect($anomalies)->toHaveCount(1);
    expect(AnomalieAppel::where('seance_id', $this->seance->id)->exists())->toBeTrue();
});

it('ne crée pas d\'anomalie si l\'appel a déjà été fait', function () {
    Presence::create(['seance_id' => $this->seance->id, 'eleve_id' => 1, 'statut' => 'present']);

    $anomalies = $this->service->detecterAnomalies();

    expect($anomalies)->toHaveCount(0);
    expect(AnomalieAppel::where('seance_id', $this->seance->id)->exists())->toBeFalse();
});

it('ne recrée pas l\'anomalie si elle existe déjà (idempotent)', function () {
    $this->service->detecterAnomalies();
    $anomalies = $this->service->detecterAnomalies();

    expect($anomalies)->toHaveCount(1);
    expect(AnomalieAppel::where('seance_id', $this->seance->id)->count())->toBe(1);
});
