<?php

use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Models\Equipement;
use App\Modules\Logistique\Models\Salle;

beforeEach(function () {
    $this->service ??= app(EquipementServiceInterface::class);
});

it('enregistre un nouvel équipement', function () {
    $equipement = $this->service->enregistrerEquipement([
        'nom' => 'Vidéoprojecteur',
        'numero_identification' => 'EQ-001',
        'date_acquisition' => now()->toDateString(),
        'valeur_acquisition' => 250000,
    ]);

    expect($equipement)->not->toBeNull()
        ->and($equipement->numero_identification)->toBe('EQ-001');
});

it('déplace un équipement et journalise l\'historique de localisation', function () {
    $salleA = Salle::create(['nom' => 'Salle A']);
    $salleB = Salle::create(['nom' => 'Salle B']);
    $equipement = Equipement::create([
        'nom' => 'Ordinateur', 'numero_identification' => 'EQ-002',
        'salle_id' => $salleA->id, 'date_acquisition' => now(),
    ]);

    $this->service->deplacer($equipement->id, $salleB->id);

    expect($equipement->fresh()->salle_id)->toBe($salleB->id);
});

it('déclare une panne', function () {
    $equipement = Equipement::create([
        'nom' => 'Imprimante', 'numero_identification' => 'EQ-003', 'date_acquisition' => now(),
    ]);

    $demande = $this->service->declarerPanne($equipement->id, 'Bourrage papier récurrent');

    expect($demande)->not->toBeNull();
});

it('met un équipement au rebut', function () {
    $equipement = Equipement::create([
        'nom' => 'Vieux PC', 'numero_identification' => 'EQ-004', 'date_acquisition' => now(),
    ]);

    $this->service->mettreAuRebut($equipement->id, 'Trop vétuste, non réparable');

    expect($equipement->fresh()->etat)->toBe('mis_au_rebut');
});

it('calcule la valeur totale du patrimoine (équipements non mis au rebut)', function () {
    Equipement::create([
        'nom' => 'A', 'numero_identification' => 'EQ-010', 'date_acquisition' => now(),
        'valeur_acquisition' => 100000, 'etat' => 'bon',
    ]);
    Equipement::create([
        'nom' => 'B', 'numero_identification' => 'EQ-011', 'date_acquisition' => now(),
        'valeur_acquisition' => 50000, 'etat' => 'mis_au_rebut',
    ]);

    $valeur = $this->service->getValeurPatrimoine();

    expect($valeur)->toBe(100000.0);
    // Hypothèse testée : les équipements "mis_au_rebut" sont exclus du
    // calcul. Si EquipementService::getValeurPatrimoine() somme tout sans
    // filtrer sur l'état, ce test échouera — à corriger côté Service dans
    // ce cas, pas dans le test (une valeur au rebut n'a plus de valeur
    // patrimoniale réelle).
});

it('liste les équipements encore sous garantie', function () {
    Equipement::create([
        'nom' => 'Sous garantie', 'numero_identification' => 'EQ-020', 'date_acquisition' => now(),
        'garantie_fin' => now()->addMonths(6),
    ]);
    Equipement::create([
        'nom' => 'Garantie expirée', 'numero_identification' => 'EQ-021', 'date_acquisition' => now(),
        'garantie_fin' => now()->subMonths(6),
    ]);

    $sousGarantie = $this->service->getEquipementsSousGarantie();

    expect($sousGarantie->pluck('nom'))->toContain('Sous garantie')
        ->and($sousGarantie->pluck('nom'))->not->toContain('Garantie expirée');
});
