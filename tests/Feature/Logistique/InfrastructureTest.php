<?php

use App\Modules\Logistique\Contracts\InfrastructureServiceInterface;
use App\Modules\Logistique\Models\Salle;
use App\Modules\Logistique\Models\TravauxInfrastructure;

beforeEach(function () {
    $this->service ??= app(InfrastructureServiceInterface::class);
});

it('liste uniquement les salles disponibles (pas hors service)', function () {
    Salle::create(['nom' => 'Salle A', 'etat' => 'bon', 'capacite' => 30]);
    Salle::create(['nom' => 'Salle B', 'etat' => 'hors_service', 'capacite' => 30]);

    $disponibles = $this->service->getSallesDisponibles();

    expect($disponibles->pluck('nom'))->toContain('Salle A')
        ->and($disponibles->pluck('nom'))->not->toContain('Salle B');
});

it('filtre les salles disponibles par capacité minimale', function () {
    Salle::create(['nom' => 'Petite salle', 'etat' => 'bon', 'capacite' => 10]);
    Salle::create(['nom' => 'Grande salle', 'etat' => 'bon', 'capacite' => 50]);

    $disponibles = $this->service->getSallesDisponibles(capaciteMin: 30);

    expect($disponibles->pluck('nom'))->toContain('Grande salle')
        ->and($disponibles->pluck('nom'))->not->toContain('Petite salle');
});

it('déclare une salle hors service', function () {
    $salle = Salle::create(['nom' => 'Salle C', 'etat' => 'bon']);

    $this->service->declarerHorsService($salle->id, 'Fuite d\'eau au plafond');

    expect($salle->fresh()->etat)->toBe('hors_service');
});

it('planifie des travaux sur une salle', function () {
    $salle = Salle::create(['nom' => 'Salle D', 'etat' => 'bon']);

    $travaux = $this->service->planifierTravaux($salle->id, 'Réfection peinture', now()->addWeek());

    expect($travaux)->not->toBeNull()
        ->and($travaux->salle_id)->toBe($salle->id)
        ->and($travaux->statut)->toBe('planifie');
});

it('termine des travaux et met à jour le statut', function () {
    $salle = Salle::create(['nom' => 'Salle E', 'etat' => 'bon']);
    $travaux = TravauxInfrastructure::create([
        'salle_id' => $salle->id,
        'description' => 'Réparation',
        'date_debut' => now(),
        'statut' => 'en_cours',
    ]);

    $this->service->terminerTravaux($travaux->id);

    expect($travaux->fresh()->statut)->toBe('termine');
});
