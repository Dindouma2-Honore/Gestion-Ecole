<?php

use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Exceptions\ConflitEmploiDuTempsException;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\Matiere;

beforeEach(function () {
    $this->service ??= app(EmploiDuTempsServiceInterface::class);

    $this->matiere = Matiere::create([
        'nom' => 'Mathématiques', 'code' => 'MATH', 'coefficient' => 3, 'actif' => true,
    ]);

    // classe_id, enseignant_id, salle_id, annee_scolaire_id : autres modules,
    // pas de contrainte FK -> de simples entiers suffisent pour les tests.
    $this->creneauA = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00']);

    $this->donneesBase = [
        'classe_id' => 1,
        'matiere_id' => $this->matiere->id,
        'enseignant_id' => 10,
        'salle_id' => 100,
        'creneau_id' => $this->creneauA->id,
        'annee_scolaire_id' => 1,
        'actif' => true,
    ];
});

it('planifie un cours sans conflit', function () {
    $cours = $this->service->planifierCours($this->donneesBase);

    expect($cours)->not->toBeNull()
        ->and($cours->id)->not->toBeNull();
});

it('détecte un conflit enseignant sur le même créneau', function () {
    $this->service->planifierCours($this->donneesBase);

    $coursB = array_merge($this->donneesBase, ['classe_id' => 2, 'salle_id' => 200]);

    expect(fn () => $this->service->planifierCours($coursB))
        ->toThrow(ConflitEmploiDuTempsException::class);
});

it('détecte un conflit lorsque deux créneaux se chevauchent', function () {
    $this->service->planifierCours($this->donneesBase);
    $chevauchant = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:30', 'heure_fin' => '09:30']);

    $coursB = array_merge($this->donneesBase, [
        'classe_id' => 2,
        'salle_id' => 200,
        'creneau_id' => $chevauchant->id,
    ]);

    expect(fn () => $this->service->planifierCours($coursB))
        ->toThrow(ConflitEmploiDuTempsException::class);
});

it('détecte un conflit de salle', function () {
    $this->service->planifierCours($this->donneesBase);

    $coursB = array_merge($this->donneesBase, ['classe_id' => 2, 'enseignant_id' => 20]);

    expect(fn () => $this->service->planifierCours($coursB))
        ->toThrow(ConflitEmploiDuTempsException::class);
});

it('détecte un conflit de classe', function () {
    $this->service->planifierCours($this->donneesBase);

    $coursB = array_merge($this->donneesBase, ['enseignant_id' => 20, 'salle_id' => 200]);

    expect(fn () => $this->service->planifierCours($coursB))
        ->toThrow(ConflitEmploiDuTempsException::class);
});

it('permet de modifier un cours sans faux conflit avec lui-même', function () {
    $cours = $this->service->planifierCours($this->donneesBase);

    $modifie = $this->service->modifierCours($cours->id, array_merge($this->donneesBase, ['salle_id' => 999]));

    expect($modifie->id)->toBe($cours->id)
        ->and($modifie->salle_id)->toBe(999);
});
