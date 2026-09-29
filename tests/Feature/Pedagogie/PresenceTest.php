<?php

use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Exceptions\AppelDejaEffectueException;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Seance;

function creerSeancePourAppel(): Seance
{
    // uniqid() évite la collision sur matieres.code (unique) quand la
    // fonction est appelée plusieurs fois dans le même test.
    $matiere = Matiere::create([
        'nom' => 'Histoire', 'code' => 'HIST-'.uniqid(), 'coefficient' => 1, 'actif' => true,
    ]);
    $creneau = CreneauHoraire::create(['jour_semaine' => 2, 'heure_debut' => '10:00', 'heure_fin' => '11:00']);

    $emploiDuTemps = EmploiDuTemps::create([
        'classe_id' => 1, 'matiere_id' => $matiere->id, 'enseignant_id' => 10,
        'salle_id' => 100, 'creneau_id' => $creneau->id, 'annee_scolaire_id' => 1, 'actif' => true,
    ]);

    return Seance::create([
        'emploi_du_temps_id' => $emploiDuTemps->id,
        'date_seance' => now()->toDateString(),
        'enseignant_id' => 10,
        'statut' => 'programmee',
    ]);
}

beforeEach(function () {
    $this->service ??= app(PresenceServiceInterface::class);
    $this->seance = creerSeancePourAppel();
});

it('fait l\'appel et passe la séance à dispensée', function () {
    $this->service->faireAppel($this->seance->id, [1 => 'present', 2 => 'absent']);

    expect($this->seance->fresh()->statut)->toBe('dispensee');
});

it('refuse un second appel sur la même séance', function () {
    $this->service->faireAppel($this->seance->id, [1 => 'present']);

    expect(fn () => $this->service->faireAppel($this->seance->id, [1 => 'present']))
        ->toThrow(AppelDejaEffectueException::class);
});

it('calcule le taux de présence correctement', function () {
    $autreSeance = creerSeancePourAppel();

    $this->service->faireAppel($this->seance->id, [1 => 'present']);
    $this->service->faireAppel($autreSeance->id, [1 => 'absent']);

    expect($this->service->getTauxPresence(eleveId: 1, periodeId: 1))->toBe(50.0);
});
