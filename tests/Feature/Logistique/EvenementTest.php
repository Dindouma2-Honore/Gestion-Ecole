<?php

use App\Modules\Logistique\Contracts\EvenementServiceInterface;
use App\Modules\Logistique\Exceptions\AutorisationParentaleManquanteException;
use App\Modules\Logistique\Models\EvenementParticipant;

beforeEach(function () {
    $this->service ??= app(EvenementServiceInterface::class);
});

it('crée un événement', function () {
    $evenement = $this->service->creerEvenement([
        'titre' => 'Sortie pédagogique au musée',
        'date_debut' => now()->addWeek(),
        'date_fin' => now()->addWeek()->addHours(4),
        'responsable_id' => 1,
        'necessite_autorisation_parentale' => true,
    ]);

    expect($evenement)->not->toBeNull()
        ->and($evenement->statut)->toBe('planifie');
});

it('inscrit un participant à un événement', function () {
    $evenement = $this->service->creerEvenement([
        'titre' => 'Kermesse', 'date_debut' => now(), 'date_fin' => now()->addHours(3), 'responsable_id' => 1,
    ]);

    $this->service->inscrireParticipant($evenement->id, \App\Modules\Scolarite\Models\Eleve::class, 1);

    expect(EvenementParticipant::where('evenement_id', $evenement->id)->count())->toBe(1);
});

it('refuse de confirmer une participation sans autorisation parentale reçue', function () {
    $evenement = $this->service->creerEvenement([
        'titre' => 'Voyage scolaire', 'date_debut' => now(), 'date_fin' => now()->addDays(2),
        'responsable_id' => 1, 'necessite_autorisation_parentale' => true,
    ]);
    $this->service->inscrireParticipant($evenement->id, \App\Modules\Scolarite\Models\Eleve::class, 1);
    $participant = EvenementParticipant::where('evenement_id', $evenement->id)->first();

    expect(fn () => $this->service->confirmerParticipation($participant->id))
        ->toThrow(AutorisationParentaleManquanteException::class);
});

it('confirme une participation une fois l\'autorisation reçue', function () {
    $evenement = $this->service->creerEvenement([
        'titre' => 'Voyage scolaire', 'date_debut' => now(), 'date_fin' => now()->addDays(2), 'responsable_id' => 1,
    ]);
    $this->service->inscrireParticipant($evenement->id, \App\Modules\Scolarite\Models\Eleve::class, 1);
    $participant = EvenementParticipant::where('evenement_id', $evenement->id)->first();
    $participant->update(['autorisation_parentale_recue' => true]);

    // Ne doit pas lever d'exception
    $this->service->confirmerParticipation($participant->id);

    expect(true)->toBeTrue();
});

it('clôture un événement avec un compte rendu', function () {
    $evenement = $this->service->creerEvenement([
        'titre' => 'Fête de fin d\'année', 'date_debut' => now(), 'date_fin' => now()->addHours(5), 'responsable_id' => 1,
    ]);

    $this->service->cloturerEvenement($evenement->id, 'Événement réussi, forte participation.');

    expect($evenement->fresh()->statut)->toBe('termine');
});
