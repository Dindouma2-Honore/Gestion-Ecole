<?php

use App\Modules\Socle\Exceptions\TransitionStatutInvalideException;
use App\Modules\VieScolaire\Contracts\ReclamationServiceInterface;
use App\Modules\VieScolaire\Models\Reclamation;
use App\Models\User;

beforeEach(function () {
    $this->service ??= app(ReclamationServiceInterface::class);
    $this->actingAs(User::factory()->create(['statut' => 'actif']));
    $this->responsable = User::factory()->create(['statut' => 'actif']);
});

it('signale une réclamation avec un délai calculé selon la priorité', function () {
    $reclamation = $this->service->signaler('reclamation_parent', 'Problème de cantine', null, 'urgente');

    expect($reclamation)->not->toBeNull()
        ->and($reclamation->statut)->toBe('ouverte')
        ->and($reclamation->delai_reponse->isSameDay(now()->addDay()))->toBeTrue();
});

it('affecte une réclamation et génère une tâche de suivi', function () {
    $reclamation = $this->service->signaler('incident_scolaire', 'Bagarre en cour', null, 'haute');

    $this->service->affecter($reclamation->id, responsableId: $this->responsable->id);

    $reclamation->refresh();
    expect($reclamation->statut)->toBe('affectee')
        ->and($reclamation->responsable_id)->toBe($this->responsable->id)
        ->and($reclamation->tache_id)->not->toBeNull();
});

it('répond à une réclamation et passe au statut résolue', function () {
    $reclamation = $this->service->signaler('plainte', 'Retard répété', null);
    $this->service->affecter($reclamation->id, $this->responsable->id);

    $this->service->repondre($reclamation->id, 'Sujet traité avec la famille.');

    $reclamation->refresh();
    expect($reclamation->statut)->toBe('resolue')
        ->and($reclamation->reponse)->toBe('Sujet traité avec la famille.');
});

it('refuse de clôturer une réclamation encore ouverte (transition invalide)', function () {
    $reclamation = $this->service->signaler('plainte', 'Test', null);

    expect(fn () => $this->service->cloturer($reclamation->id))
        ->toThrow(TransitionStatutInvalideException::class);
});

it('liste les réclamations en retard', function () {
    $reclamation = Reclamation::create([
        'type' => 'plainte',
        'description' => 'En retard',
        'priorite' => 'normale',
        'delai_reponse' => now()->subDay(),
        'statut' => 'ouverte',
        'created_at' => now(),
    ]);

    $enRetard = $this->service->getReclamationsEnRetard();

    expect($enRetard->pluck('id'))->toContain($reclamation->id);
});